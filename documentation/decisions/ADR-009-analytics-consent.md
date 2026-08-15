# ADR-009 — Tracking GA4 des achats : consentement strict et déduplication côté serveur

## Statut

Acceptée et vérifiée (composer qa + npm test verts, vérification manuelle du bandeau de
consentement et du dataLayer restant à faire avec un vrai conteneur GTM — voir
`documentation/technique/google-tag-manager.md`).

## Contexte

Le produit veut mesurer les achats de crédits via Google Tag Manager / GA4, avec deux exigences
non négociables : (1) l'URL de succès Stripe ne doit jamais, seule, être traitée comme une preuve
de paiement — seule une confirmation backend déclenche l'événement `purchase` ; (2) un même achat
ne doit jamais produire deux événements `purchase`, même en cas de double onglet, de
rafraîchissement, ou de changement de langue. Deux décisions structurantes ont dû être prises que
le brief ne tranche pas explicitement lui-même.

## Décision

### 1. Le conteneur GTM ne charge jamais sans clic explicite sur « Accepter »

Considéré et rejeté : le [Consent Mode v2](https://developers.google.com/tag-platform/security/guides/consent) de Google, qui charge `gtag.js`/GTM immédiatement et lui transmet un signal
`consent: denied` tant que l'utilisateur n'a pas répondu. Rejeté parce que cette approche envoie
quand même une requête réseau vers les serveurs de Google avant tout consentement (le signal de
refus lui-même transite par eux) — ce qui ne satisfait pas l'exigence du produit d'un chargement
strictement nul avant acceptation.

Retenu à la place : le `<script>` du conteneur GTM (`https://www.googletagmanager.com/gtm.js?id=...`)
n'est injecté dans le DOM que par deux chemins explicites — au chargement de la page si
`localStorage.gtm_consent === 'granted'` (visite revenante), ou immédiatement au clic sur
« Accepter » (visite en cours) — voir `templates/base.html.twig`. Refuser, ou ignorer le bandeau,
signifie qu'aucune requête vers `googletagmanager.com` n'est jamais émise. `window.dataLayer.push`
(voir `assets/app/src/lib/dataLayer.ts`) a lieu sans condition dans tout le code applicatif : c'est
un tableau local, inoffensif tant que rien n'est chargé pour le lire — la seule porte de sortie
réelle vers Google est le `<script>` du conteneur lui-même.

Conséquence acceptée : un événement poussé dans `dataLayer` avant que l'utilisateur ait répondu au
bandeau n'est jamais renvoyé rétroactivement après un « Accepter » tardif sur une **autre**
page vue — il n'est traité que si le conteneur est déjà chargé au moment du push, ou se charge
avant que la page ne soit quittée. C'est un compromis délibéré (voir `google-tag-manager.md`) :
mieux vaut manquer un événement que d'en dupliquer un ou d'en envoyer un sans consentement.

### 2. La déduplication est garantie côté serveur, jamais confiée à GA4

Considéré et rejeté : s'appuyer sur la déduplication propre à GA4 par `transaction_id` (GA4
ignore un second événement `purchase` portant un `transaction_id` déjà vu). Rejeté parce que ce
comportement n'est pas documenté comme une garantie contractuelle par Google (fenêtre de
déduplication non précisée, pas de confirmation synchrone), et qu'il place la seule protection
contre un double comptage entièrement hors de ce dépôt — invérifiable par nos propres tests.

Retenu à la place : `CreditPurchase::analyticsTrackedAt` (nullable, posé une seule fois via
`markAnalyticsTracked()`, même idiome que `completedAt`/`markCompleted()`), et un seul endpoint
(`POST /api/billing/checkout/{publicId}/confirm-analytics`, voir
`ConfirmAnalyticsTrackingAction`) qui **décide et revendique dans la même opération atomique** :
`SELECT ... FOR UPDATE` sur la ligne verrouille deux appels concurrents (deux onglets ouverts sur
la même page de succès), le premier reçoit `claimed: true` et pousse l'événement, le second reçoit
`claimed: false` et ne pousse rien — même s'il affiche le même écran de succès. Rejeté
explicitement : une conception à deux endpoints (un `GET` de lecture puis un `POST` de
confirmation séparés) envisagée dans une itération précédente de ce travail, où le frontend
décidait de pousser en lisant l'état d'un `GET` avant d'appeler un `POST` distinct — un vrai
défaut de conception trouvé en relecture : deux onglets peuvent tous les deux lire un `GET`
« pas encore tracké » avant qu'aucun des deux `POST` n'ait eu le temps de revendiquer, et donc
tous les deux pousser l'événement. Décider et revendiquer doivent être une seule opération, pas
deux appels séquencés côté client.

L'identifiant `transaction_id` (`smartgpx_{CreditPurchase::publicId}`) est entièrement dérivé côté
serveur au moment de la persistance — jamais généré côté client (`crypto.randomUUID()` aurait
produit un identifiant différent à chaque revendication tentée, rendant toute déduplication
illusoire).

## Conséquences

- Le premier onglet à confirmer un achat donné « gagne » la revendication ; un second onglet
  ouvert simultanément affiche le même écran de succès sans jamais savoir qu'il a « perdu » — un
  détail invisible pour l'utilisateur, jamais un bug côté paiement (le crédit est déjà accordé de
  façon indépendante, par le webhook Stripe signé — voir
  [ADR-006](ADR-006-billing-provider.md)).
- Refuser le consentement ne bloque et ne ralentit jamais le paiement lui-même : les deux
  préoccupations restent strictement séparées dans le code (l'échec d'un appel
  `confirm-analytics` après paiement confirmé n'affecte jamais l'écran affiché au client — voir
  `assets/app/src/entries/billingCheckoutSuccess.ts`).
- La politique de confidentialité (`templates/legal/privacy.html.twig`) affirmait auparavant
  n'utiliser aucun traceur analytics — mise à jour dans ce même travail pour refléter fidèlement
  ce mécanisme, plutôt que de le construire sans corriger une promesse devenue fausse.

## Vérification effectuée

`composer qa` vert (262 tests, y compris le double-appel de confirmation qui ne revendique
qu'une fois — voir `BillingCheckoutStatusControllerTest`) ; `npm run typecheck && npm run test &&
npm run build` verts côté frontend (fonctions de décision pures testées sans mock réseau — voir
`assets/app/src/billing/checkoutSuccessPolling.test.ts`). Vérification manuelle de bout en bout
(bandeau affiché une fois, refus n'émettant aucune requête vers `googletagmanager.com`,
acceptation chargeant le conteneur immédiatement) reste à faire avec un vrai `GTM_CONTAINER_ID` —
voir `documentation/technique/google-tag-manager.md`.
