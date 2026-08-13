# Fonctionnalités

## Conversion payante : Google Maps → GPX

L'utilisateur colle un lien Google Maps (`google.com/maps/dir/...` ou `maps.app.goo.gl/...`).
SmartGPX prend en charge origine, destination, étapes intermédiaires, adresses ou coordonnées
GPS, et les modes de transport (voiture, marche, vélo, etc.). Un GPX 1.1 standard est généré,
avec les étapes intermédiaires préservées comme waypoints significatifs quand les données le
permettent. **Statut : Phase 2, non implémenté.**

Règle critique de non-régression : une chaîne comme `49.051624,2.0093594` est reconnue comme des
coordonnées, jamais envoyée comme une adresse littérale au fournisseur de routing (voir
`documentation/technique/google-maps-to-gpx.md`, à rédiger en Phase 2).

## Outils gratuits (aucun ne consomme de crédit)

Tous les outils suivants sont détaillés dans `documentation/fonctionnel/free-tools.md` et
s'exécutent côté navigateur (voir
[ADR-003](../decisions/ADR-003-browser-conversions.md)) — **statut Phase 5/6, non implémenté**,
seule la page d'accueil les liste déjà (Phase 1) :

GPX → Google Maps, GPX Simplify, GPX Merge, KML → GPX, KMZ → GPX, TCX ↔ GPX, FIT ↔ GPX,
GeoJSON ↔ GPX, GPX Viewer.

## Extension Chrome

Détaillée dans `documentation/fonctionnel/chrome-extension.md`. **Statut : Phase 3, non
implémenté.**

## Compte et authentification

Inscription, connexion, déconnexion, vérification d'e-mail, mot de passe oublié/réinitialisation
— **implémenté en Phase 1** (voir `documentation/fonctionnel/authentification.md`). Google
Sign-In n'est pas implémenté en Phase 1 (schéma prêt pour une extension ultérieure sans
migration destructive).

## Crédits et paiement

1 conversion Google Maps → GPX gratuite à l'inscription, puis 1 crédit par conversion réussie
(jamais en cas d'échec). Packs de crédits payants sans abonnement. **Statut : Phase 4, non
implémenté** — voir `documentation/fonctionnel/pricing.md` pour la grille tarifaire exacte.

## Admin

Interface simple pour opérer le produit (utilisateurs, ledger de crédits, achats, conversions
échouées, métriques de base). **Statut : Phase 8, non implémenté.**
