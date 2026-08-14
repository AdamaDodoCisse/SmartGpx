# ADR-007 — Accès admin et modèle de contrôle d'accès

## Statut

Acceptée et implémentée (Phase 8).

## Contexte

Le brief demande une « interface simple pour opérer le produit » — utilisateurs, ledger de
crédits, achats, conversions échouées, métriques de base. Aucune notion de rôle au-delà de
`ROLE_USER` n'existait avant cette phase : `User::$roles` valait toujours `['ROLE_USER']`,
`setRoles()` existait déjà sur l'entité mais n'était appelée nulle part. Il fallait décider
comment accorder et vérifier un rôle admin, où placer le code de chaque écran, et comment
combler un vrai trou du modèle de données : les conversions échouées n'étaient enregistrées nulle
part.

## Décision

### Rôle et provisionnement

`ROLE_ADMIN` est accordé **uniquement via une commande console**,
`bin/console app:user:promote-admin <email>` (`src/Identity/Command/PromoteUserToAdminCommand.php`,
`src/Identity/Action/PromoteUserToAdminAction.php`) — jamais depuis l'interface admin elle-même.
Choix délibéré : exposer une action « promouvoir un utilisateur en admin » dans l'UI créerait une
surface d'auto-élévation de privilèges dès la v1, pour un bénéfice nul (le nombre d'admins reste
faible et le provisionnement est une opération rare, ponctuelle).

Chaque méthode de contrôleur admin porte `#[IsGranted('ROLE_ADMIN')]`, exactement comme
`AccountExtensionController`/`ConvertGoogleMapsController` gardent déjà leurs routes avec
`#[IsGranted('ROLE_USER')]`. Aucune entrée `access_control` n'a été ajoutée à
`security.yaml` : ce n'était déjà le mécanisme de garde nulle part ailleurs dans l'application,
et l'introduire spécifiquement pour `/admin` aurait établi un second modèle de contrôle d'accès à
maintenir en parallèle du premier.

### Placement du code : le domaine qui possède l'entité mutée

Règle explicite (déjà implicite dans le code existant — `GrantWelcomeCreditAction` vit dans
`Usage`, pas `Identity`, bien que déclenchée par un événement `Identity`) : **une Action vit dans
le domaine de l'entité qu'elle mute, jamais dans le domaine de son appelant.**

| Action | Domaine | Entité mutée |
|---|---|---|
| `PromoteUserToAdminAction` | `Identity/Action/` | `User` |
| `LogConversionFailureAction` | `Conversion/Action/` | `ConversionFailure` |
| `GrantAdminCreditAdjustmentAction` | `Usage/Action/` | `CreditAccount`/`CreditTransaction` |
| `CreateCreditPackAction`, `UpdateCreditPackAction` | `Billing/Action/` | `CreditPack` |
| `ComputeAdminMetricsAction` | `Admin/Action/` | aucune — lecture transversale pure |

`src/Admin/` ne contient donc que les contrôleurs, les templates, et l'unique lecture qui
n'appartient à aucun domaine existant (les métriques du tableau de bord, qui agrègent des
compteurs venant de cinq repositories différents).

### `ConversionFailure` : une entité séparée, pas des colonnes nullables

Aucune tentative de conversion échouée n'était enregistrée avant cette phase : le docblock de
`Conversion` garantissait explicitement « seules les conversions réussies sont enregistrées », et
`ConvertGoogleMapsToGpxAction` relâche la réservation de crédit puis relance l'exception sans
jamais rien persister.

Plutôt que d'ajouter des colonnes nullables (`status`, `failureReason`…) à `Conversion` — ce qui
aurait cassé cette garantie pour toute ligne existante ou future —, une entité dédiée
`ConversionFailure` (`src/Conversion/Entity/`) a été créée : toujours entièrement peuplée,
immuable après création (même style que `CreditTransaction`, pas de `TimestampableTrait` puisque
`updatedAt` n'aurait aucun sens). Pas de `publicId` : c'est un écran de liste admin uniquement,
rien ne référence une ligne précise par URL — même raisonnement que `CreditTransaction`, qui n'en
a pas non plus.

`LogConversionFailureAction` est appelée depuis **les deux** contrôleurs qui invoquent
`ConvertGoogleMapsToGpxAction` — `ConvertGoogleMapsController` (web) et
`ExtensionConversionController` (extension Chrome) — dont les quatre branches `catch` sont
identiques mot pour mot. Ne câbler que le contrôleur web aurait silencieusement sous-compté
toute tentative échouée déclenchée depuis l'extension. Seules 4 raisons distinguables sont
loguées (`ConversionFailureReason` : `unsupported_url`, `insufficient_credits`,
`route_not_found`, `provider_unavailable`), miroir exact des clés de traduction
`conversion.error.*` déjà existantes — `invalid_csrf`/`too_many_requests` sont des gardes avant
toute tentative réelle, pas des tentatives de conversion, et ne sont donc jamais loguées.

### Ajustement manuel de crédits : toujours un crédit, jamais un débit

`GrantAdminCreditAdjustmentAction` rejette tout montant `<= 0` et écrit une `CreditTransaction`
de type `ADMIN_ADJUSTMENT` — la valeur d'enum réservée depuis la Phase 2 spécifiquement pour cet
usage (voir ADR-002), inutilisée jusqu'ici. Aucun chemin de débit manuel n'existe : retirer des
crédits à un compte n'est pas un besoin produit exprimé, et l'ajouter sans qu'il soit demandé
aurait élargi la surface de ce qu'un admin peut faire au-delà du strict nécessaire.

## Limite connue (documentée, non traitée en Phase 8)

Aucune colonne d'audit (« ajusté par quel admin ») n'existe sur `CreditTransaction` — la ligne de
ledger prouve qu'un ajustement a eu lieu et son montant, mais pas son auteur. Amélioration
raisonnable pour une V2 (nécessiterait une colonne nullable `adminUserId`, sur le même modèle que
`conversionId`/`creditPurchaseId` — un entier sans relation Doctrine, pas un prérequis bloquant
pour la version « simple » demandée par le brief.

## Vérification effectuée

`composer qa` clean (195 tests, cs-fixer et PHPStan niveau 8). Vérification manuelle : promotion
d'un compte de développement réel via la commande, connexion, parcours des 5 écrans, confirmation
d'un 403 pour un compte `ROLE_USER` simple sur chacun, déclenchement réel des 4 raisons d'échec de
conversion et vérification de leur apparition dans `/admin/conversions/failed`, ajustement manuel
de crédits confirmé sur le solde et le ledger d'un compte cible, création/édition/désactivation
d'un pack via `/admin/credit-packs` et confirmation de son effet sur `/pricing`.
