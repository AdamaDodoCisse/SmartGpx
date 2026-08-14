# Architecture

## Vue d'ensemble

SmartGPX est composé de deux moteurs complémentaires (voir
`documentation/fonctionnel/vision-produit.md`) :

- un **moteur d'acquisition** : outils gratuits, exécutés côté navigateur (voir
  [ADR-003](../decisions/ADR-003-browser-conversions.md)) ;
- un **moteur de revenu** : conversion payante Google Maps → GPX, crédits, extension Chrome.

Le rendu public repose sur Symfony/Twig avec des îlots React pour l'interactivité (voir
[ADR-004](../decisions/ADR-004-seo-rendering.md)).

## Organisation du code backend (`src/`)

Le code est organisé **par domaine métier**, pas par type technique. Chaque domaine a la même
forme interne :

```
src/<Domaine>/
  Entity/         # entités Doctrine
  Repository/      # repositories Doctrine
  Enum/            # enums du domaine
  Request/         # DTO d'entrée (une classe = un cas d'usage en entrée)
  Form/            # Symfony Form quand une saisie utilisateur passe par Twig
  Action/          # une classe = un cas d'usage métier, execute(...): Result
  Mailer/          # envoi d'e-mails propres au domaine
  Controller/      # pont HTTP, aucune logique métier
```

`src/Identity/` (Phase 1) suit cette forme et sert de référence pour les domaines suivants ;
`src/Routing/`, `src/Conversion/`, `src/Usage/` (Phase 2), `src/Extension/` (Phase 3) et
`src/Billing/` (Phase 4) l'adaptent avec quelques sous-dossiers techniques supplémentaires
propres à leur usage (`ValueObject/`, `Provider/`, `Result/`, `Parser/`, `Gpx/`,
`EventListener/`) — même esprit que `Identity/Mailer/`. `Admin/` reste à construire (Phase 8).

`src/Shared/` contient uniquement du code réellement transverse à tous les domaines (ex.
`Shared/Doctrine/TimestampableTrait.php`) — à ne pas utiliser comme fourre-tout.

## Pattern Action

Un contrôleur ne contient **aucune orchestration métier**. Le flux est toujours :

```
Request (HTTP)
  → DTO (Request\...)
  → Validation (symfony/validator, attributs Assert sur le DTO)
  → Action (une classe = un cas d'usage, execute(...): Result)
  → Response (rendu Twig ou redirection)
```

Exemple (`src/Identity/Controller/RegistrationController.php`) : le contrôleur construit le
DTO `RegisterUserRequest`, le fait valider par un `Symfony\Component\Form\FormInterface`, puis
délègue tout le travail à `RegisterUserAction::execute()`. L'Action ne connaît ni la requête
HTTP, ni le formulaire — uniquement le DTO — ce qui permet de rejouer la même logique métier
depuis un autre point d'entrée (API JSON, CLI, extension Chrome) sans dupliquer le code.

**Exception documentée** : l'authentification (login) n'a pas d'Action dédiée — le firewall
Symfony (`form_login`) *est* le cas d'usage, il n'y a aucune logique métier supplémentaire à
extraire.

## Interfaces aux frontières externes uniquement

Une interface + implémentation(s) n'est créée qu'à une véritable frontière externe (API tierce,
prestataire de paiement). Frontières identifiées par le brief produit :

- `RoutingProviderInterface` (implémentée, Phase 2 — voir
  [ADR-001](../decisions/ADR-001-routing-provider.md)) — abstraction du fournisseur de routing
  (Google Routes en premier, puis OpenRouteService/GraphHopper/OSRM/Valhalla possibles sans
  réécrire le moteur de conversion) ;
- `BillingProviderInterface` (implémentée, Phase 4 — voir
  [ADR-006](../decisions/ADR-006-billing-provider.md)) — abstraction du prestataire de paiement
  (Stripe).

Ne pas créer d'interface spéculative « au cas où » pour un composant qui n'a qu'une seule
implémentation réelle (ex. pas d'`AuthenticationProviderInterface` en Phase 1 pour un Google
Sign-In qui n'est pas encore implémenté — voir
`documentation/fonctionnel/authentification.md`).

## Frontend (`assets/app/`)

Voir `documentation/technique/frontend.md` pour le détail Vite/React/i18n. Les convertisseurs de
formats « gratuits » vivent **dans le frontend** (`assets/app/src/gps/`), pas dans Symfony —
conséquence directe de l'[ADR-003](../decisions/ADR-003-browser-conversions.md).

## Base de données

Voir `documentation/technique/base-de-donnees.md`.

## Sécurité

Voir `documentation/technique/securite.md`.
