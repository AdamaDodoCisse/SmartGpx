# ADR-006 — Fournisseur de paiement : Stripe Checkout

## Statut

Acceptée et vérifiée par tests fonctionnels, y compris la vérification de signature Stripe
réelle (Phase 4). Également vérifiée manuellement de bout en bout contre un vrai compte Stripe en
mode test (session Checkout réelle, paiement par carte de test, webhook réel reçu et traité,
crédits accordés, idempotence confirmée sur une double livraison réelle) — voir
`documentation/technique/stripe.md`.

## Contexte

L'achat de packs de crédits (voir `documentation/fonctionnel/pricing.md`) doit accepter un
paiement par carte et, une fois celui-ci confirmé, créditer le compte de façon sûre — y compris
face à une notification de confirmation reçue plusieurs fois (Stripe livre ses webhooks « au
moins une fois », jamais exactement une fois). Le registre de crédits (`src/Usage/`) a été conçu
en Phase 2 pour être prêt pour cette phase : `CreditTransactionType::PURCHASE`/`::REFUND`
existent déjà dans l'enum précisément pour éviter une migration cassante ici — voir
[[ADR-002-credit-ledger]].

## Décision

### Abstraction

`App\Billing\Provider\BillingProviderInterface` est la seule façade que le reste de
l'application connaît :

```php
interface BillingProviderInterface
{
    public function createCheckoutSession(
        string $customerEmail,
        int $amountCents,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
        string $idempotencyKey,
    ): CheckoutSession;

    public function parseWebhookEvent(string $rawPayload, ?string $signatureHeader): WebhookEvent;
}
```

`CheckoutSession`/`WebhookEvent` (`App\Billing\Result\`) sont des DTO indépendants de tout
fournisseur — aucun type `\Stripe\*` ne fuit hors de `StripeBillingProvider`. Deux
implémentations existent : `StripeBillingProvider` (réelle, via le SDK officiel
`stripe/stripe-php`) et `FakeBillingProvider` (déterministe, file d'attente scriptable, utilisée
exclusivement en environnement de test via l'alias `when@test` de `config/services.yaml`) — même
pattern que `RoutingProviderInterface` (voir [[ADR-001-routing-provider]]).

**SDK officiel plutôt que HTTP brut** : la création de session passe par
`Stripe\StripeClient::checkout->sessions->create()`, et surtout la vérification de signature
webhook par `Stripe\Webhook::constructEvent()`. Recalculer soi-même un HMAC tolérant au
décalage d'horloge est exactement le genre de code sensible à la sécurité que ce projet évite de
réinventer (même logique que le choix `hash('sha256', ...)` plutôt qu'un HMAC maison pour les
jetons d'extension, voir [[ADR-005-extension-authentication]]).

### Contrat Stripe

- **Checkout hébergé (redirection), pas Elements/Payment Intents intégrés** : aucune page ne
  charge `stripe.js` aujourd'hui, et [[ADR-004-seo-rendering]] impose que Twig rende tout, React
  ne servant que d'îlots ponctuels — embarquer un formulaire de carte aurait exigé un nouvel
  îlot pour aucun bénéfice produit au lancement. `BillingCheckoutController::create()` crée la
  session puis redirige (302) vers `CheckoutSession::redirectUrl`, l'intégralité de la saisie de
  carte se déroulant sur le domaine de Stripe.
- **`price_data` dynamique, pas d'objet `Price` Stripe pré-provisionné** : `pricing.md` exige de
  pouvoir changer prix/crédits sans réécrire de code métier ; un `Price` Stripe créé une fois se
  désynchroniserait dès qu'une ligne `CreditPack` change sans modification manuelle côté
  dashboard Stripe. Le prix, la devise et le nom du produit sont recalculés à chaque session
  depuis `CreditPack`.
- `success_url` contient le littéral `{CHECKOUT_SESSION_ID}` (substitué par Stripe), construit
  par concaténation de chaîne — **jamais** comme paramètre de route Symfony, qui encoderait les
  accolades en URL.
- Un `idempotency_key` frais (UUIDv7) est fourni à chaque appel de création de session —
  protège contre une nouvelle tentative réseau du SDK dupliquant la session, pas contre un
  double clic de l'utilisateur (deux requêtes HTTP distinctes créeraient deux sessions Stripe
  distinctes, sans risque de double débit puisque seul un paiement réellement effectué peut
  aboutir).

### Idempotence des livraisons webhook

`CreditPurchase` (`src/Billing/Entity/`) suit une session Stripe de sa création (`PENDING`) à sa
confirmation par webhook (`COMPLETED`), avec une contrainte unique sur
`stripe_checkout_session_id`. `GrantPurchasedCreditsAction` :

```php
$purchase = $this->creditPurchaseRepository->findOneByStripeCheckoutSessionIdForUpdate($stripeCheckoutSessionId);
// SELECT ... FOR UPDATE, à l'intérieur d'une transaction déjà ouverte : sérialise deux
// livraisons concurrentes du même événement.

if ($purchase->isCompleted()) {
    return; // livraison dupliquée : no-op idempotent
}
// ... crédite le compte, insère la ligne de ledger PURCHASE, marque COMPLETED, commit
```

Vérifié par
`GrantPurchasedCreditsActionTest::testADuplicateWebhookDeliveryDoesNotDoubleGrantCredits` et son
équivalent fonctionnel `BillingWebhookControllerTest` (deux livraisons du même événement à
travers la vraie route/firewall → un seul crédit accordé).

### Firewall du webhook

Aucun `User` à authentifier — Stripe n'est pas un utilisateur, la confiance vient d'une
assertion cryptographique, pas d'une identité applicative. `config/packages/security.yaml`
déclare un firewall dédié, `api_billing_webhook` (`pattern: ^/billing/webhook/`,
`security: false` — même forme que le firewall `dev`), déclaré avant `main`. Toute la
vérification a lieu dans `BillingWebhookController::stripe()` via
`BillingProviderInterface::parseWebhookEvent()`, appelé sur `$request->getContent()` (corps
brut) — **aucun middleware de désérialisation JSON ne doit jamais être attaché à cette route
avant cette vérification**, sous peine de casser la vérification de signature (qui porte sur
l'octet exact du corps envoyé par Stripe).

### Gestion des erreurs

- Signature manquante ou invalide → `InvalidWebhookSignatureException` → HTTP 400 (Stripe ne
  reprogramme pas de nouvelle tentative sur un 400 : la requête est rejetée définitivement, ce
  qui est correct pour une signature invalide).
- Type d'événement reconnu mais hors périmètre (`WebhookEventType::UNHANDLED` — tout sauf
  `checkout.session.completed` cette phase) → HTTP 200 sans action : Stripe considère
  l'événement livré, pas d'erreur.
- Session Stripe inconnue en base (`CreditPurchaseNotFoundException`) → HTTP 200 + log d'erreur
  serveur : une anomalie de données, pas quelque chose qu'une nouvelle tentative de Stripe
  résoudrait.
- Toute autre exception (base de données indisponible, etc.) remonte sans être interceptée →
  HTTP 500 → Stripe reprogramme automatiquement une nouvelle tentative. Comportement voulu.
- Échec de création de session (`Stripe\Exception\ApiErrorException`) → journalisé en détail
  côté serveur, exposé comme `BillingProviderUnavailableException` (message générique) ;
  `BillingCheckoutController::create()` redirige vers `/pricing` avec un message traduit.
- **La page de succès (`/billing/checkout/success`) ne crédite jamais de compte** :
  `session_id` est un paramètre de requête devinable/rejouable, ce n'est pas un canal de
  confiance. Seul le webhook signé accorde des crédits ; la page affiche le solde si la session
  est déjà `COMPLETED`, sinon un message générique invitant à patienter quelques secondes.
- **Un remboursement Stripe (`charge.refunded`) n'est pas géré cette phase et ne reprend
  jamais automatiquement des crédits.** Au moment d'un remboursement, les crédits peuvent déjà
  avoir été dépensés (une conversion déjà effectuée, un GPX déjà téléchargé — un service déjà
  rendu ne se « re-dépense » pas) : reprendre des crédits automatiquement est une question de
  politique commerciale, pas un détail technique. Choix documenté par défaut : résolution
  manuelle via le type de ledger déjà provisionné `ADMIN_ADJUSTMENT` (Phase 8), jamais un débit
  automatique déclenché par webhook.

## Vérification effectuée

`StripeBillingProviderTest` construit un événement `checkout.session.completed` au format Stripe
réel, le signe avec un vrai HMAC (`t=...,v1=...`) et appelle `parseWebhookEvent()` directement —
prouve que l'intégration SDK fonctionne réellement, pas seulement le double déterministe.
`BillingWebhookControllerTest` fait de même à travers la vraie route et le vrai firewall (payload
signé réel → 200 + crédit accordé ; signature invalide → 400 ; en-tête absent → 400) et prouve
l'idempotence face à une livraison dupliquée. `BillingCheckoutControllerTest` couvre la création
de session (redirection + ligne `PENDING` créée), un pack inconnu/inactif (404), un jeton CSRF
invalide (403), un utilisateur anonyme (redirection vers la connexion) et un échec du
fournisseur (redirection vers `/pricing` avec message).

Vérification manuelle en mode test Stripe réel réalisée : session Checkout créée via le vrai
flux `/pricing` → `/billing/checkout/{publicId}`, payée avec la carte `4242 4242 4242 4242` sur
la page hébergée par Stripe, webhook réel reçu via `stripe listen` (signature Stripe authentique,
pas une simulation), `CreditPurchase` passée à `COMPLETED`, compte crédité de exactement le
nombre de crédits du pack. Le même événement (`stripe events resend`) redistribué une seconde
fois via l'API Stripe réelle n'a pas re-crédité le compte ni créé de seconde ligne de ledger —
confirme l'idempotence face à une redélivrance réelle, pas seulement simulée en test.

## Alternatives envisagées

**Stripe Elements/Payment Intents embarqués** — différé, pas rejeté : demanderait un nouvel îlot
React et sortirait du principe « Twig rend tout » sans bénéfice produit clair au lancement ;
`BillingProviderInterface` n'empêche pas une implémentation future qui exposerait un
`client_secret` à un îlot de paiement si le besoin apparaît. **Objets `Price` Stripe
pré-provisionnés** — différé : plus de visibilité côté rapports Stripe, mais introduit une
seconde source de vérité pour le prix, contraire à l'exigence explicite de `pricing.md`.
**Reprise automatique de crédits sur remboursement** — délibérément non retenue par défaut (voir
Gestion des erreurs ci-dessus) : c'est une décision produit, à reconsidérer explicitement si le
volume de remboursements le justifie un jour, pas une omission technique.
