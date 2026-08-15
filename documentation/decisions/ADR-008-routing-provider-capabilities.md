# ADR-008 — Options avancées de routage : capabilities, coût, et flux de sélection d'itinéraire

## Statut

Acceptée et vérifiée en fonctionnement réel (Phase 10).

## Contexte

Le brief produit demande d'étendre le convertisseur Google Maps → GPX avec des options avancées
(mode de transport, évitements, trafic, optimisation/type des étapes, itinéraires alternatifs,
route économe en carburant, estimation des péages, presets) sans complexifier le parcours
standard, et en gardant l'architecture prête pour un futur fournisseur non-Google (voir
[ADR-001](ADR-001-routing-provider.md)). Trois décisions structurantes ont dû être prises que le
brief ne tranche pas explicitement lui-même.

## Décision

### 1. Les capabilities vivent sur le fournisseur, pas dans une config statique

`RoutingProviderInterface::capabilities(): RoutingProviderCapabilities` plutôt qu'un fichier de
configuration listant ce qui est supporté. **Justification** : ce que Google Routes API supporte
dépend du mode de transport demandé (`routeModifiers`/`routingPreference` uniquement pour
DRIVE/TWO_WHEELER, route économe en carburant uniquement pour DRIVE) — une config statique
externe au fournisseur se déconnecterait inévitablement de la réalité de ce que
`GoogleRoutesProvider` envoie réellement. En les faisant porter par le fournisseur lui-même,
`capabilities()` et le code qui construit la requête HTTP ne peuvent pas diverger : ils vivent
dans la même classe. `FakeRoutingProvider` déclare le même jeu de capabilities pour ne pas fausser
les tests d'un panneau qui déciderait d'afficher/masquer une option selon les capabilities.

### 2. Le filtrage des options non supportées est silencieux, jamais une erreur

`App\Conversion\Service\GoogleMapsRouteOptionsMapper` retire une option demandée que les
capabilities actives ne supportent pas, plutôt que de rejeter la requête. **Justification** :
le brief est explicite — "ne jamais afficher une option que le provider actif ne supporte pas".
Un rejet en erreur romprait cette promesse dès qu'un client (futur usage API, extension future)
enverrait une option valide pour un fournisseur mais pas pour un autre ; le filtrage silencieux
garde le contrat simple : la réponse reflète toujours ce qui a *effectivement* été appliqué
(`routeOptionsApplied` dans `ConversionJsonPresenter`), jamais ce qui a été demandé en vain.

### 3. Alternatives et route économe en carburant : un flux en deux temps, pas une troisième méthode d'interface

Considéré et rejeté : ajouter `computeRoute()` (une route) et `computeAlternativeRoutes()`
(plusieurs) comme deux méthodes distinctes de `RoutingProviderInterface`. Rejeté parce que Google
renvoie les alternatives et la route de référence économe en carburant dans la **même** réponse
`computeRoutes()` que la route principale (`routes[]`, plusieurs entrées) — deux méthodes
d'interface auraient forcé soit un appel HTTP dupliqué, soit une API artificiellement compliquée
juste pour exposer une distinction que Google ne fait pas lui-même.

Retenu à la place : une seule méthode (`computeRoutes()`, toujours `RouteComputation` avec
`list<RouteResult>`), et la décision "l'utilisateur doit-il choisir ?" déplacée au niveau
applicatif (`Conversion/`), pas au niveau du fournisseur :

- `RouteOptions::requestsMultipleRoutes()` (vrai si `computeAlternativeRoutes` ou
  `showFuelEfficientRoute`) détermine si le contrôleur web appelle le flux à une étape
  (`ConvertGoogleMapsToGpxAction`, comportement historique inchangé) ou le flux à deux temps
  (`PreviewGoogleMapsRoutesAction` puis `ExportPreviewedRouteAction`).
- Le flux à deux temps ne réserve/consomme un crédit qu'à l'export, sur l'itinéraire réellement
  choisi — jamais sur un calcul de comparaison. L'aperçu est mis en cache (Redis, pool
  `cache.app` déjà utilisé par le rate limiter, TTL 10 minutes, scellé à l'id utilisateur) plutôt
  que recalculé à l'export : Google ne garantit pas de renvoyer exactement les mêmes alternatives
  à deux appels successifs, donc réexécuter `computeRoutes()` à l'export pourrait facturer un
  itinéraire différent de celui affiché à l'utilisateur.

### 4. Classification de coût désormais visible, jamais encore facturée

`RoutingFeatureCostTier` (`STANDARD`/`ADVANCED`, voir `RouteOptionsCostClassifier`) est calculée
et exposée (réponse JSON, entité `Conversion`) mais **le prix reste fixe à 1 crédit quelle que
soit la classification**. Décision explicite, pas un oubli : le brief demande cette classification
pour un usage *ultérieur* du système de crédits, et introduire une facturation différenciée
maintenant élargirait `ReserveCreditAction`/`CreditAccountRepository`/`ConsumeReservedCreditAction`
(actuellement câblées sur un montant fixe de 1 à trois endroits distincts — voir
`documentation/technique/credit-system.md`) sans que le produit ait encore tranché la grille
tarifaire réelle. Livrer la classification sans la facturation permet de vérifier qu'elle reflète
correctement l'usage réel avant de l'attacher à un prix.

## Conséquences

- Un futur `ValhallaRoutingProvider` avec un jeu de fonctionnalités plus restreint (pas
  d'alternatives, pas de route économe en carburant) fonctionnerait sans qu'aucune interface ni
  composant frontend ne change — voir la mise à jour de
  [ADR-001](ADR-001-routing-provider.md#alternatives-envisagées).
- Le flux à deux temps introduit un état serveur de courte durée (le cache Redis) qui n'existait
  pas avant — accepté parce que borné dans le temps (10 minutes), à usage unique, et scellé par
  utilisateur (`RoutePreviewNotFoundException` générique si l'aperçu est expiré, inconnu, ou
  appartient à quelqu'un d'autre — jamais de distinction qui révélerait l'un ou l'autre cas).
- L'extension Chrome n'implémentant pas d'UI de choix d'itinéraire dans cette phase, aucun
  contrôleur `/preview`/`/export` équivalent n'existe côté `App\Extension\` — un choix de
  périmètre, pas une limitation technique : `ConvertGoogleMapsToGpxAction` reste appelable telle
  quelle si cette UI est construite plus tard.

## Vérification effectuée

Flux complet exercé contre l'API Google Routes réelle (pas simulée) : conversion standard avec
évitement des péages (préférence appliquée, visible dans le résultat) ; flux de sélection
d'itinéraire avec alternatives et route économe en carburant réelles, sélection d'un itinéraire
non par défaut, export, GPX généré et téléchargé, exactement 1 crédit débité. `composer qa` vert
(236 tests) incluant la régression Address/Coordinates étendue au nouveau pipeline
`RouteWaypoint`.
