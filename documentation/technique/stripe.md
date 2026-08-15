# Stripe

**Statut : implémenté (Phase 4), vérifié par tests fonctionnels y compris la vérification de
signature réelle, et vérifié manuellement de bout en bout contre un vrai compte Stripe en mode
test** (session Checkout réelle, paiement par carte de test, webhook réel reçu et traité,
idempotence confirmée sur une redélivrance réelle via `stripe events resend`) — voir
[ADR-006](../decisions/ADR-006-billing-provider.md#vérification-effectuée).

## Où c'est

```
src/Billing/
  Entity/{CreditPack, CreditPurchase}.php
  Enum/{CreditPackBadge, CreditPurchaseStatus, WebhookEventType}.php
  Repository/{CreditPackRepository, CreditPurchaseRepository}.php
  Result/{CheckoutSession, WebhookEvent}.php
  Provider/{BillingProviderInterface, StripeBillingProvider, FakeBillingProvider}.php
  Action/{CreateCheckoutSessionAction, GrantPurchasedCreditsAction}.php
  Exception/{BillingProviderException, BillingProviderUnavailableException, InvalidWebhookSignatureException, CreditPurchaseNotFoundException, CreditPackNotFoundException}.php
  Controller/{BillingCheckoutController, BillingWebhookController}.php
```

## Flux d'achat

```
/pricing → formulaire « Acheter » (CSRF billing_checkout)
  → POST /billing/checkout/{publicId du CreditPack}
      → CreateCheckoutSessionAction : appelle Stripe, crée CreditPurchase (PENDING)
      → redirection 302 vers la session Checkout hébergée par Stripe
  → carte saisie sur checkout.stripe.com — jamais sur ce domaine
  → Stripe redirige vers /billing/checkout/success?session_id=...
      → affiche l'état de l'achat, ne crédite jamais directement (voir plus bas)
  → Stripe POST /billing/webhook/stripe (checkout.session.completed), en parallèle,
    potentiellement plusieurs fois
      → BillingProviderInterface::parseWebhookEvent() vérifie la signature
      → GrantPurchasedCreditsAction : idempotent, crédite le compte une seule fois
```

Voir [ADR-006](../decisions/ADR-006-billing-provider.md) pour le raisonnement complet : pourquoi
Checkout hébergé plutôt qu'Elements, pourquoi `price_data` dynamique plutôt qu'un `Price`
Stripe pré-provisionné, le mécanisme d'idempotence exact, et pourquoi la page de succès ne
crédite jamais de compte elle-même.

## `BillingProviderInterface`

```php
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
```

`StripeBillingProvider` (réelle, via `stripe/stripe-php`) et `FakeBillingProvider` (file
d'attente scriptable, `when@test` dans `config/services.yaml`) — aucun type `\Stripe\*` ne fuit
hors de `StripeBillingProvider`.

## Idempotence

Voir [ADR-006](../decisions/ADR-006-billing-provider.md#idempotence-des-livraisons-webhook).
Résumé : `credit_purchase.stripe_checkout_session_id` est unique en base,
`GrantPurchasedCreditsAction` verrouille la ligne (`SELECT ... FOR UPDATE`) dans une transaction
et n'agit que si son statut n'est pas déjà `COMPLETED`.

## Ce qui n'est pas encore fait

- `payment_intent.payment_failed` et `charge.refunded` ne sont pas traités —
  `WebhookEventType::UNHANDLED` répond 200 sans action. Un remboursement ne reprend jamais de
  crédits automatiquement (décision produit documentée dans l'ADR, pas un oubli).
- Pas de clé publiable (`STRIPE_PUBLISHABLE_KEY`) : le flux Checkout hébergé ne charge aucun code
  Stripe côté client.
- Pas de méthodes de paiement asynchrones (virements, etc.) : `payment_method_types` reste au
  défaut carte, ce qui évite d'avoir à gérer `checkout.session.async_payment_succeeded`.

## Mode live (production)

**En place depuis le 2026-08-15.** La production (`https://smartgpx.com`, voir
[deploiement.md](deploiement.md)) tourne sur le compte Stripe live :

- `STRIPE_SECRET_KEY=sk_live_...` dans le `.env.local` du serveur (jamais committé, jamais
  affiché dans un chat/log). Aucun objet Product/Price n'a été créé dans le catalogue Stripe —
  le checkout utilise `price_data` inline depuis la table `credit_pack` (6 packs seedés).
- Endpoint webhook live `we_1U4ngeAV88HZtMx1pCpOVgo5` →
  `https://smartgpx.com/billing/webhook/stripe`, abonné uniquement à
  `checkout.session.completed`, version d'API `2026-07-29.dahlia` (alignée sur stripe-php
  v21.2). Son secret `whsec_...` est dans `STRIPE_WEBHOOK_SECRET` côté serveur.
- Vérifications effectuées au passage en live, sans débit réel : `GET /v1/balance` avec la clé
  live (`livemode: true`), POST non signé sur le endpoint → 400 (signature exigée), création
  puis expiration d'une session Checkout live avec les mêmes paramètres que
  `StripeBillingProvider`. Le premier paiement réel se surveille dans les logs de livraison du
  webhook (Dashboard Stripe → Développeurs → Webhooks).
- À noter : le compte Stripe est en EUR ; les packs sont facturés en USD (choix du seed),
  Stripe convertit lors du règlement.

## Tester en local

```bash
stripe listen --forward-to localhost:8000/billing/webhook/stripe
```

Copier le secret de signature affiché (`whsec_...`) dans `.env.local`
(`STRIPE_WEBHOOK_SECRET=...`), et la clé secrète de test du dashboard Stripe
(`STRIPE_SECRET_KEY=sk_test_...`). Carte de test : `4242 4242 4242 4242`, toute date future,
tout CVC.

Voir [ADR-006](../decisions/ADR-006-billing-provider.md#vérification-effectuée) pour le détail de
la vérification déjà réalisée, automatisée et manuelle.
