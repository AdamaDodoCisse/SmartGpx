# Pricing

**Statut : grille définie par le produit, paiement non implémenté avant la Phase 4.** La page
`/pricing` (Phase 1) affiche déjà cette grille à titre informatif, sans achat possible.

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

## Architecture (à implémenter en Phase 4)

Bien que la grille ci-dessus soit fixe au lancement, l'architecture doit permettre de modifier
prix, nombre de crédits, statut actif/inactif, ordre d'affichage et libellés (Most Popular, Best
Value) sans réécrire de code métier — pas de valeurs numériques éparpillées dans le code (voir
`documentation/technique/credit-system.md`, à rédiger en Phase 4).
