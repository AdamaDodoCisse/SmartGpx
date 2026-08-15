# Routing provider

**Statut : implémenté (Phase 2 ; étendu Phase 10 — options avancées).** Détail de conception
complet dans [ADR-001](../decisions/ADR-001-routing-provider.md) (fondations) et
[ADR-008](../decisions/ADR-008-routing-provider-capabilities.md) (capabilities, options avancées)
— ce document reste un point d'entrée rapide côté code.

## Où c'est

```
src/Routing/
  ValueObject/{RouteLocation, Address, Coordinates, RouteLocationParser}.php
  ValueObject/{RouteOptions, RouteModifiers, RouteWaypoint, VehicleProfile, RoutePresetResolution}.php
  Enum/{TravelMode, RoutingPreference, RouteDetail, WaypointType, RoutePreset, RoutingFeatureCostTier, VehicleEmissionType}.php
  Provider/{RoutingProviderInterface, GoogleRoutesProvider, FakeRoutingProvider}.php
  Result/{RouteResult, RouteLeg, RoutePoint, RouteComputation, RouteTollEstimate, RoutingProviderCapabilities}.php
  Service/{RouteOptionsCostClassifier, RoutePresetOptionsResolver}.php
  Exception/{RoutingProviderException, RouteNotFoundException, RoutingProviderUnavailableException, TooManyWaypointsException}.php
```

## L'interface, depuis la Phase 10

```php
interface RoutingProviderInterface
{
    public function computeRoutes(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,       // list<RouteWaypoint>
        TravelMode $travelMode,
        RouteOptions $options = new RouteOptions(),
    ): RouteComputation;            // list<RouteResult> + RoutingFeatureCostTier

    public function capabilities(): RoutingProviderCapabilities;
}
```

`computeRoute()` (singulier, une seule route en retour) a été remplacée par `computeRoutes()` —
`RouteComputation::primary()` redonne l'équivalent de l'ancien retour pour le flux à une étape
(voir `google-maps-to-gpx.md`). `new RouteOptions()` (valeur par défaut du paramètre) reproduit
exactement le comportement d'avant les options avancées : aucun appelant existant n'a besoin de
changer pour continuer à fonctionner à l'identique.

`capabilities()` est la pièce centrale de l'extensibilité : elle déclare ce que le fournisseur
actif sait réellement faire (modes de transport supportés, évitements, trafic, optimisation des
étapes, alternatives, route économe en carburant, estimation des péages, nombre maximum d'étapes
intermédiaires). Le frontend (voir `assets/app/src/components/conversion/AdvancedRouteOptions.tsx`)
et le mapping serveur (`App\Conversion\Service\GoogleMapsRouteOptionsMapper`) n'affichent/n'appliquent
jamais une option que ces capabilities ne déclarent pas supportée — jamais une erreur, un filtrage
silencieux. `RoutingCapabilitiesController` (`GET /api/routing/capabilities`, public) expose la
même donnée en JSON ; `HomeController` l'injecte aussi directement dans le HTML de la page
d'accueil pour éviter un aller-retour réseau supplémentaire au montage de l'îlot React.

## Ajouter un second fournisseur

1. Implémenter `RoutingProviderInterface` (ex. `ValhallaRoutingProvider`).
2. Mapper `RouteLocation`/`RouteWaypoint`/`TravelMode`/`RouteOptions` vers le format du nouveau
   fournisseur, et sa réponse vers `RouteComputation`/`RouteResult`/`RouteLeg`/`RoutePoint` —
   aucun type spécifique au fournisseur ne doit sortir de cette classe.
3. Renvoyer, dans `capabilities()`, uniquement ce que ce fournisseur sait *réellement* faire —
   c'est ce qui permet à un fournisseur avec un jeu de fonctionnalités plus restreint (pas
   d'alternatives, pas de route économe en carburant, moins de modes de transport) de fonctionner
   sans qu'aucune interface ni aucun composant frontend ne change : ils s'adaptent déjà aux
   capabilities déclarées.
4. Changer l'alias dans `config/services.yaml` (actuellement
   `App\Routing\Provider\RoutingProviderInterface: '@App\Routing\Provider\GoogleRoutesProvider'`).

Rien ailleurs dans l'application (Conversion, Usage, contrôleurs) ne référence
`GoogleRoutesProvider` directement.

## Tests

`FakeRoutingProvider` est l'implémentation utilisée dans `when@test`
(`config/services.yaml`) — aucun test n'appelle jamais l'API Google réelle. Elle expose
`queue()` (accepte `RouteComputation`, `RouteResult` ou une exception — un `RouteResult` seul est
enveloppé automatiquement dans une `RouteComputation` à un seul élément) pour scripter un résultat,
`callCount` pour vérifier qu'aucun appel externe n'a eu lieu, et un fixture multi-routes
déterministe (`defaultMultiRouteFixture()`) utilisé dès qu'une option demande plusieurs
itinéraires, sans dépendre du réseau.

`GoogleRoutesProvider` est testé via `Symfony\Component\HttpClient\MockHttpClient` (voir
`tests/Routing/Provider/GoogleRoutesProviderTest.php`) : forme exacte du JSON envoyé pour les 4
combinaisons adresse/coordonnées, marquage `via` des étapes VIA, envoi conditionnel de
`routeModifiers`/`routingPreference` (uniquement DRIVE/TWO_WHEELER), alternatives et route
économe en carburant avec labels et estimation de péage, dépassement du nombre maximum d'étapes,
mapping des erreurs, absence de fuite de la clé API dans les messages d'erreur.

## Configuration

`config/packages/http_client.yaml` définit un client HTTP nommé (`google.routes.client`,
timeouts courts) autowiré comme `HttpClientInterface $googleRoutesClient` par convention Symfony.
`GOOGLE_ROUTES_API_KEY` : placeholder dans `.env`, vraie valeur dans `.env.local` (jamais
committée).
