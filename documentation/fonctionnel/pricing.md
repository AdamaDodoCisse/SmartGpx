# Pricing

**Statut : implémenté (Phase 4).** La page `/pricing` lit la grille depuis `CreditPack` (base de
données) et permet l'achat via Stripe Checkout — voir `documentation/technique/stripe.md` et
[ADR-006](../decisions/ADR-006-billing-provider.md).

## Principe

- 1 conversion Google Maps → GPX gratuite à l'inscription (une seule fois, pas récurrente sauf
  changement explicite de modèle économique).
- Ensuite, 1 crédit = 1 conversion Google Maps → GPX réussie. Une conversion échouée ne consomme
  aucun crédit.
- Seule la conversion Maps → GPX consomme des crédits ; tous les outils gratuits (voir
  `documentation/fonctionnel/free-tools.md`) en sont exclus par construction.
- Les crédits n'expirent jamais et fonctionnent identiquement sur le web et dans l'extension
  Chrome.
- Aucun abonnement récurrent, aucune offre à vie au lancement.

## Grille de lancement

Pricing officiel de lancement (Phase 13) — aucun achat n'ayant eu lieu avant cette grille, elle
n'a jamais eu besoin de coexister avec un pricing antérieur.

| Pack | Prix | Crédits | Prix / conversion | Badge |
|---|---|---|---|---|
| Starter | 4,99 $ | 10 | 0,499 $ | |
| Popular | 9,99 $ | 100 | 0,100 $ | Most Popular |
| Power | 29,99 $ | 500 | 0,060 $ | |

## Architecture

La grille ci-dessus vit dans la table `credit_pack`
(`credits`/`price_cents`/`badge`/`display_order`/`active`) — prix, nombre de crédits, statut
actif/inactif et ordre d'affichage se modifient sans réécrire de code métier. Éditable depuis
`/admin/credit-packs` (Phase 8) — voir `documentation/fonctionnel/admin.md`. Chaque pack a
également un identifiant stable pour l'analytics (`starter_10`/`popular_100`/`power_500`, dérivé
du nombre de crédits — voir `App\Billing\CreditPackSlug` et
`documentation/technique/google-tag-manager.md`), distinct de son `publicId` (UUID) utilisé pour
le routing/checkout.

Aucun `Price`/`Product` Stripe n'est pré-provisionné : le prix est recalculé à chaque session de
paiement depuis cette table (`price_data` dynamique — voir
[ADR-006](../decisions/ADR-006-billing-provider.md)). Changer la grille ne nécessite donc jamais
d'action côté tableau de bord Stripe.
