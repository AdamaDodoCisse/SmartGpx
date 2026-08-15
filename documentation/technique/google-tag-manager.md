# Google Tag Manager / GA4

**Statut : implémenté, vérifié par tests automatisés (double-appel de confirmation, forme du
payload, propriété de l'achat vérifiée). La vérification manuelle de bout en bout avec un vrai conteneur GTM
(chargement conditionné au consentement, événement visible en DebugView GA4) reste à faire** —
voir « Tester en local » ci-dessous. Voir
[ADR-009](../decisions/ADR-009-analytics-consent.md) pour le raisonnement architectural complet
(pourquoi un hard-gate plutôt que le Consent Mode v2 de Google, pourquoi la déduplication est
garantie côté serveur plutôt que confiée à GA4).

## Principe : l'URL de succès n'est jamais une preuve de paiement

Même principe que documenté dans `BillingCheckoutController` pour le crédit lui-même : arriver
sur `/billing/checkout/success` (`/fr/paiement/succes`) ne prouve rien, l'URL est devinable et
rejouable. L'événement GA4 `purchase` n'est donc jamais déclenché par la simple présence sur cette
page — uniquement par une confirmation explicite du backend, via
`POST /api/billing/checkout/{publicId}/confirm-analytics`.

## Où c'est

```
src/Billing/
  Action/ConfirmAnalyticsTrackingAction.php   # décide ET revendique dans une seule transaction verrouillée
  Controller/BillingCheckoutStatusController.php
  Result/AnalyticsConfirmationResult.php
  Entity/CreditPurchase.php                    # + analyticsTrackedAt / markAnalyticsTracked()

assets/app/src/
  billing/checkoutSuccessPolling.ts            # logique pure, testable sans mock réseau
  entries/billingCheckoutSuccess.ts            # collage DOM (fetch, boucle de sondage, bascule d'état)
  lib/dataLayer.ts                             # window.dataLayer.push, sans condition de consentement

templates/
  billing/success.html.twig                    # 3 blocs pré-traduits : verifying / paid / unconfirmed
  base.html.twig                                # bandeau de consentement + injection conditionnelle du script GTM
  pricing/_pack_grid.html.twig                  # data-* pour begin_checkout
  pricing/index.html.twig                       # pricing_viewed
```

## Flux

```
/pricing → événement "pricing_viewed" (au chargement)
  → clic "Buy" → événement "begin_checkout" (avant la soumission du formulaire, voir base.html.twig)
      → redirection Stripe Checkout hébergé
  → paiement
  → webhook Stripe signé → crédit accordé (voir documentation/technique/stripe.md, inchangé par ce travail)
  → redirection vers /billing/checkout/success?session_id=...
      → assets/app/src/entries/billingCheckoutSuccess.ts sonde en boucle
        POST /api/billing/checkout/{publicId}/confirm-analytics
      → tant que status=pending : réessaie (intervalle fixe, nombre de tentatives borné)
      → status=completed :
          claimed=true  (première confirmation) → dataLayer.push({event: "purchase", ...}), affiche "paid"
          claimed=false (revisite, déjà tracké)  → n'envoie rien, affiche quand même "paid"
      → status=failed, ou nombre de tentatives épuisé : affiche "unconfirmed" (jamais présenté
        comme un échec de paiement — voir ADR-009)
```

`ConfirmAnalyticsTrackingAction` verrouille la ligne `CreditPurchase`
(`findOneByPublicIdForUpdate`, même mécanisme que `GrantPurchasedCreditsAction` pour le crédit
lui-même) : décider si l'achat est payé ET marquer `analyticsTrackedAt` se fait dans la même
section critique, donc deux appels concurrents (deux onglets) ne peuvent jamais tous les deux
recevoir `claimed: true`.

## Catalogue des événements dataLayer

| Événement | Déclenché depuis | Champs |
| --- | --- | --- |
| `pricing_viewed` | `templates/pricing/index.html.twig` | — |
| `begin_checkout` | écouteur délégué dans `base.html.twig`, sur la soumission d'un formulaire `[data-begin-checkout]` | `currency`, `value`, `items: [{item_id, item_name, item_category, price, quantity}]` |
| `purchase` | `assets/app/src/entries/billingCheckoutSuccess.ts`, uniquement si `claimed: true` | `transaction_id`, `currency`, `value`, `items: [{item_id, item_name, item_category, price, quantity}]` |

`transaction_id` = `smartgpx_{CreditPurchase::publicId}` — dérivé côté serveur, stable, jamais
généré côté client. `item_id` = `publicId` du `CreditPack` acheté ; `item_name` toujours en
anglais (`"{credits} SmartGPX Credits"`), jamais traduit — un `item_name` différent par locale
fragmenterait le reporting GA4 pour un même produit.

## Sécurité du payload

Le payload `purchase`/`begin_checkout` ne contient **que** des champs commerciaux agrégés
(montant, devise, nombre de crédits, identifiants de produit). Ne sont **jamais** envoyés : e-mail,
nom, numéro de carte, adresse de facturation, ou tout identifiant Stripe interne — voir le test
`testTheAnalyticsPayloadContainsOnlyAllowlistedEcommerceFields` qui vérifie l'ensemble exact des
clés retournées par l'API.

## Bandeau de consentement

`localStorage.gtm_consent` (`'granted'` / `'declined'` / absent). Le script du conteneur GTM
(`https://www.googletagmanager.com/gtm.js?id=...`) n'est injecté dans le DOM que si la valeur est
`'granted'` — jamais par défaut, jamais via le mécanisme Consent Mode de Google (voir
[ADR-009](../decisions/ADR-009-analytics-consent.md) pour pourquoi). Refuser (ou ignorer le
bandeau) signifie qu'aucune requête n'est jamais envoyée à `googletagmanager.com`. Pas de
`<noscript>` GTM standard : sans JavaScript, ce bandeau ne peut de toute façon jamais recueillir de
consentement, donc GTM ne doit pas non plus se charger inconditionnellement dans ce cas.

## Configurer GTM côté Google

1. **GTM ("Custom Event Trigger", pas un déclencheur sur le chemin d'URL)** : dans le conteneur,
   créer un déclencheur *Custom Event*, nom `CE - purchase`, nom d'événement `purchase`. **Ne
   jamais** créer un déclencheur du type « Page Path equals /billing/checkout/success » — la
   présence sur cette page ne prouve rien (voir plus haut) ; seul l'événement `purchase` du
   dataLayer, poussé après confirmation backend, doit déclencher le tag.
2. Créer un tag **GA4 Event**, nom d'événement `purchase`, associé au déclencheur `CE - purchase`
   ci-dessus, avec les variables de la couche de données (`transaction_id`, `currency`, `value`,
   `items`) mappées aux champs standard e-commerce GA4.
3. Même principe pour `begin_checkout` (déclencheur Custom Event `CE - begin_checkout`) et
   `pricing_viewed` si un tag dédié est souhaité.

## Configurer les identifiants

`GTM_CONTAINER_ID` (`.env.local`, pas un secret — un ID de conteneur est visible dans le code
source de toute page qui le charge, même traitement que `EXTENSION_CHROME_ID`). Sans valeur réelle
configurée (`changeme` ou vide), le bandeau de consentement et l'injection du script GTM ne
s'affichent jamais — la fonctionnalité reste totalement inerte, `dataLayer.push` continue de
fonctionner (tableau local) sans effet visible côté Google.

## Tester en local

Avec un vrai `GTM_CONTAINER_ID` dans `.env.local` : recharger une page, vérifier que le bandeau
s'affiche une seule fois ; cliquer « Refuser », confirmer via l'onglet Réseau du navigateur
qu'aucune requête vers `googletagmanager.com` n'est jamais émise même après un achat complet ;
recharger, cliquer « Accepter », confirmer que le script du conteneur apparaît immédiatement dans
le DOM ; effectuer un achat de test Stripe complet
(`stripe listen --forward-to localhost:8000/billing/webhook/stripe`), vérifier dans GTM
Preview/GA4 DebugView qu'un seul événement `purchase` apparaît, y compris après un
rafraîchissement de la page de succès ou un changement de langue EN↔FR sur cette même page.
