# Routing provider

**Statut : implémenté (Phase 2).** Détail de conception complet dans
[ADR-001](../decisions/ADR-001-routing-provider.md) — ce document reste un point d'entrée
rapide côté code.

## Où c'est

```
src/Routing/
  ValueObject/{RouteLocation, Address, Coordinates, RouteLocationParser}.php
  Enum/TravelMode.php
  Provider/{RoutingProviderInterface, GoogleRoutesProvider, FakeRoutingProvider}.php
  Result/{RouteResult, RouteLeg, RoutePoint}.php
  Exception/{RoutingProviderException, RouteNotFoundException, RoutingProviderUnavailableException}.php
```

## Ajouter un second fournisseur

1. Implémenter `RoutingProviderInterface` (ex. `OpenRouteServiceProvider`).
2. Mapper `RouteLocation`/`TravelMode` vers le format du nouveau fournisseur, et sa réponse vers
   `RouteResult`/`RouteLeg`/`RoutePoint` — aucun type spécifique au fournisseur ne doit sortir de
   cette classe.
3. Changer l'alias dans `config/services.yaml` (actuellement
   `App\Routing\Provider\RoutingProviderInterface: '@App\Routing\Provider\GoogleRoutesProvider'`).

Rien ailleurs dans l'application (Conversion, Usage, contrôleurs) ne référence
`GoogleRoutesProvider` directement.

## Tests

`FakeRoutingProvider` est l'implémentation utilisée dans `when@test`
(`config/services.yaml`) — aucun test n'appelle jamais l'API Google réelle. Elle expose
`queue()` pour scripter un résultat ou une exception, et `callCount` pour vérifier qu'aucun
appel externe n'a eu lieu (ex. cas crédit insuffisant).

`GoogleRoutesProvider` est testé via `Symfony\Component\HttpClient\MockHttpClient` (voir
`tests/Routing/Provider/GoogleRoutesProviderTest.php`) : forme exacte du JSON envoyé pour les 4
combinaisons adresse/coordonnées, mapping des erreurs, absence de fuite de la clé API dans les
messages d'erreur.

## Configuration

`config/packages/http_client.yaml` définit un client HTTP nommé (`google.routes.client`,
timeouts courts) autowiré comme `HttpClientInterface $googleRoutesClient` par convention Symfony.
`GOOGLE_ROUTES_API_KEY` : placeholder dans `.env`, vraie valeur dans `.env.local` (jamais
committée).
