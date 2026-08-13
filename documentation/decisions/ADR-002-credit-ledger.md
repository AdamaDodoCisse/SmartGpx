# ADR-002 — Registre de crédits (ledger)

## Statut

Acceptée et vérifiée en fonctionnement réel (Phase 2). **Correction par rapport au plan de la
Phase 1** : le stub initial de cette ADR indiquait « à rédiger en Phase 4 », en supposant que le
ledger de crédits n'était nécessaire qu'avec les achats Stripe. En réalité, la conversion
Google Maps → GPX (Phase 2) doit déjà décompter un crédit de façon sûre sous concurrence dès la
première conversion gratuite — le ledger et son modèle de concurrence sont donc conçus et livrés
ici, pas en Phase 4.

## Contexte

Règle produit : 1 conversion gratuite à l'inscription, puis 1 crédit par conversion réussie —
jamais en cas d'échec. Le brief exige explicitement un registre immuable plutôt qu'un simple
solde muable (`User.creditBalance`), et une garantie que « 1 crédit restant + 2 requêtes
simultanées » ne puisse jamais produire 2 conversions payantes réussies.

## Décision

### Modèle de données

Deux entités dans `src/Usage/Entity/` :

- **`CreditAccount`** (1:1 avec `User`) : `balance` (crédits immédiatement dépensables) et
  `reserved` (crédits actuellement bloqués par une conversion en cours) — deux compteurs
  entiers mis en cache pour la performance, mais dont l'exactitude est garantie par construction
  (voir concurrence ci-dessous), pas par confiance aveugle.
- **`CreditTransaction`** : ligne de ledger **immuable** (jamais modifiée après création) —
  `type` (`WELCOME|PURCHASE|CONVERSION|REFUND|ADMIN_ADJUSTMENT`), `amount` (signé), `balanceAfter`,
  référence optionnelle vers une conversion.

Invariant vérifiable à tout moment : `balance + reserved == SUM(credit_transaction.amount)` pour
un compte donné (voir `tests/Usage/Repository/CreditAccountRepositoryTest.php`).

**Seuls `WELCOME` et `CONVERSION` sont produits par du code en Phase 2.** `PURCHASE`/`REFUND`
(Phase 4 — Stripe) et `ADMIN_ADJUSTMENT` (Phase 8 — admin) existent déjà dans l'enum pour que le
schéma n'ait pas besoin d'une migration cassante quand ces phases arriveront — mais aucun chemin
de code actuel ne les construit.

`CreditTransaction.conversionId` référence `App\Conversion\Entity\Conversion` via un simple
entier, **sans relation Doctrine ni contrainte FK** — décision délibérée pour que le domaine
`Usage` reste indépendant du domaine `Conversion` (même logique que `Identity` qui ignore
l'existence de `Usage`, voir `documentation/technique/architecture.md`).

### Concurrence : UPDATE conditionnel atomique

`CreditAccountRepository::reserveOne()` exécute une unique instruction SQL, hors de toute
transaction ORM :

```sql
UPDATE credit_account
SET balance = balance - 1, reserved = reserved + 1
WHERE user_id = :userId AND balance >= 1
```

Le nombre de lignes affectées (0 ou 1) indique si la réservation a réussi. **Choix retenu plutôt
qu'un `SELECT ... FOR UPDATE` explicite** : sous InnoDB, un `UPDATE ... WHERE` effectue une
« lecture courante » — verrou de ligne et réévaluation de la clause `WHERE` contre la dernière
valeur committée, pas contre un instantané de transaction. Deux requêtes concurrentes sur la
même ligne se sérialisent donc automatiquement : celle qui commit en premier « gagne » le
décrément, la seconde réévalue `balance >= 1` sur la valeur déjà décrémentée et échoue
correctement si plus rien n'est disponible. C'est plus simple (un aller-retour au lieu de deux,
aucun risque d'oublier d'envelopper `SELECT` et `UPDATE` dans la même transaction) et cela
généralise correctement : avec 2 crédits disponibles et 2 requêtes simultanées, les deux
réussissent — comportement correct, à ne pas confondre avec le scénario à 1 crédit que le brief
demande explicitement de ne jamais laisser passer deux fois.

Le verrou de ligne n'est tenu que le temps de cette unique instruction (`reserveOne` n'est pas
enveloppée dans une transaction explicite) — pas pendant tout l'appel externe, lent, à l'API de
routing qui suit.

### Cycle réservation → consommation/relâchement

- **`ReserveCreditAction`** : réserve 1 crédit ou lève `InsufficientCreditsException` — rien
  d'externe n'est encore appelé à ce stade.
- **`ConsumeReservedCreditAction`** : appelée uniquement après un appel réussi au fournisseur de
  routing. Dans une transaction explicite (`Connection::beginTransaction()`/`commit()`) :
  persiste la `Conversion`, décrémente `reserved` (même mécanisme atomique conditionnel), insère
  la ligne de ledger `CONVERSION` avec le solde résultant. **Seul chemin de code qui écrit une
  ligne `CONVERSION`.**
- **`ReleaseReservedCreditAction`** : appelée si le fournisseur de routing échoue. Restaure
  `balance`, décrémente `reserved` — **aucune ligne de ledger n'est écrite**. Rien n'a jamais été
  définitivement consommé : une conversion échouée coûte réellement 0 crédit.

### Crédit de bienvenue

`Identity\Event\UserRegisteredEvent` est déclenché par `RegisterUserAction` juste après le
`flush()` de l'utilisateur. `Usage\EventListener\GrantWelcomeCreditOnRegistrationListener`
(`#[AsEventListener]`) y réagit et appelle `GrantWelcomeCreditAction`, qui crée le
`CreditAccount` (solde initial 1) et la ligne de ledger `WELCOME` correspondante. Ce découplage
par événement — plutôt qu'un appel direct depuis `RegisterUserAction` — évite de faire dépendre
le domaine `Identity` (fondamental, utilisé par tout le reste) du domaine `Usage`.

## Limite connue (documentée, non traitée en Phase 2)

Si le processus PHP s'interrompt brutalement entre `ReserveCreditAction` et
`ConsumeReservedCreditAction`/`ReleaseReservedCreditAction` (erreur fatale, OOM, déploiement en
cours de requête), le crédit reste bloqué dans `reserved` sans récupération automatique. Un job
de réconciliation périodique (relâcher les réservations plus vieilles qu'un certain délai)
serait une amélioration raisonnable côté opérations (Phase 8), pas un prérequis de la Phase 2 —
`reserved` est un compteur agrégé, pas une ligne par réservation avec horodatage, donc cette
réconciliation nécessiterait d'abord un changement de modèle.

## Vérification effectuée

Scénario complet vérifié en conditions réelles : inscription → 1 crédit de bienvenue affiché →
conversion réussie (API Google réelle) → solde à 0 → seconde tentative → HTTP 402, aucun débit
supplémentaire.
