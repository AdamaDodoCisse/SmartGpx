# Extension Chrome

**Statut : implémenté (Phase 3).** Détail technique dans
`documentation/technique/chrome-extension.md`.

## Pourquoi

C'est la différenciation principale de SmartGPX face aux références du marché (voir
`documentation/fonctionnel/vision-produit.md`) : le parcours le plus rapide pour convertir un
itinéraire Google Maps en GPX doit se faire **sans quitter Google Maps**.

## Flux cible

```
Google Maps (itinéraire ouvert)
  → ouverture du popup SmartGPX
  → itinéraire détecté automatiquement
  → Export GPX
  → terminé
```

Aussi proche que possible d'un seul clic.

## Authentification

Compte SmartGPX (pas de mot de passe stocké dans l'extension) via un jeton d'autorisation
révocable (`ExtensionAuthorization`), avec renouvellement/ré-authentification sécurisés.
L'utilisateur peut révoquer l'accès Chrome depuis son compte à tout moment.

## Permissions

Permissions minimales — `activeTab` et `storage` en priorité. Pas d'accès à tout l'historique de
navigation, pas d'accès large à tous les sites sans nécessité. Chaque permission demandée devra
être documentée dans `chrome-extension/PRIVACY_DISCLOSURE.md` au moment de l'implémentation.

## Crédits

Fonctionnent à l'identique du web : 1 crédit consommé par conversion réussie, 1ère conversion
gratuite affichée explicitement si elle n'a pas encore été utilisée.
