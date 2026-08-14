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

| Pack | Prix | Crédits | Prix / conversion | Badge |
|---|---|---|---|---|
| Starter | 4,99 $ | 10 | 0,499 $ | |
| Popular | 9,99 $ | 100 | 0,100 $ | Most Popular |
| — | 16,99 $ | 200 | 0,085 $ | |
| Value | 39,99 $ | 500 | 0,080 $ | Best Value |
| — | 79,99 $ | 1 000 | 0,080 $ | |
| — | 699,99 $ | 10 000 | 0,070 $ | |

## Architecture

La grille ci-dessus est seedée par migration dans la table `credit_pack`
(`credits`/`price_cents`/`badge`/`display_order`/`active`) — prix, nombre de crédits, statut
actif/inactif, ordre d'affichage et libellés se modifient sans réécrire de code métier, pas de
valeurs numériques éparpillées dans le code. Pas encore d'interface d'administration pour éditer
ces lignes (Phase 8) — voir `documentation/technique/stripe.md`.
