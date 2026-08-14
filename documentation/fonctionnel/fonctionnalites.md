# Fonctionnalités

## Conversion payante : Google Maps → GPX

L'utilisateur colle un lien Google Maps (`google.com/maps/dir/...` ou `maps.app.goo.gl/...`).
SmartGPX prend en charge origine, destination, étapes intermédiaires, adresses ou coordonnées
GPS, et les modes de transport (voiture, marche, vélo, transports en commun). Un GPX 1.1 standard
est généré, avec les étapes intermédiaires préservées comme waypoints significatifs quand les
données le permettent. **Statut : implémenté (Phase 2), vérifié contre l'API Google Routes
réelle.**

Règle critique de non-régression : une chaîne comme `49.051624,2.0093594` est reconnue comme des
coordonnées, jamais envoyée comme une adresse littérale au fournisseur de routing (voir
`documentation/technique/google-maps-to-gpx.md` et
[ADR-001](../decisions/ADR-001-routing-provider.md)).

## Outils gratuits (aucun ne consomme de crédit)

Tous les outils suivants sont détaillés dans `documentation/fonctionnel/free-tools.md` et
s'exécutent côté navigateur (voir
[ADR-003](../decisions/ADR-003-browser-conversions.md)) — **statut : implémentés (Phase 5/6)** :

GPX → Google Maps, GPX Simplify, GPX Merge, KML → GPX, KMZ → GPX, TCX ↔ GPX, FIT ↔ GPX,
GeoJSON ↔ GPX, GPX Viewer.

## Extension Chrome

Détaillée dans `documentation/fonctionnel/chrome-extension.md`. **Statut : implémenté
(Phase 3), vérifié de bout en bout en Chrome réel.**

## Compte et authentification

Inscription, connexion, déconnexion, vérification d'e-mail, mot de passe oublié/réinitialisation
— **implémenté en Phase 1** (voir `documentation/fonctionnel/authentification.md`). Google
Sign-In n'est pas implémenté en Phase 1 (schéma prêt pour une extension ultérieure sans
migration destructive).

## Crédits et paiement

1 conversion Google Maps → GPX gratuite à l'inscription, puis 1 crédit par conversion réussie
(jamais en cas d'échec) — **le crédit de bienvenue et le décompte par conversion sont
implémentés et vérifiés (Phase 2)**, voir [ADR-002](../decisions/ADR-002-credit-ledger.md).
L'achat de packs de crédits payants (Stripe, sans abonnement) est **implémenté (Phase 4)** —
voir `documentation/fonctionnel/pricing.md` pour la grille tarifaire exacte, éditable depuis
`/admin/credit-packs` (Phase 8).

## Admin

Interface simple pour opérer le produit (utilisateurs, ledger de crédits, achats, conversions
échouées, métriques de base). **Statut : implémenté (Phase 8)** — voir
`documentation/fonctionnel/admin.md`.
