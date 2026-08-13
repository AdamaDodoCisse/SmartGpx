# ADR-001 — Fournisseur de routing : Google Routes API v2

## Statut

Acceptée et vérifiée en fonctionnement réel (Phase 2).

## Contexte

La conversion payante Google Maps → GPX doit calculer un itinéraire réel (distance, durée,
géométrie, points de passage résolus) à partir d'une origine, d'une destination et d'étapes
intermédiaires, chacune pouvant être une adresse littérale ou des coordonnées GPS. Le choix du
fournisseur est déjà acté par le brief produit (Google Routes) ; cette ADR documente le contrat
technique retenu et l'architecture d'abstraction qui permettra d'en changer plus tard.

## Décision

### Abstraction

`App\Routing\Provider\RoutingProviderInterface` est la seule façade que le reste de
l'application connaît :

```php
interface RoutingProviderInterface
{
    public function computeRoute(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
    ): RouteResult;
}
```

`RouteResult`/`RouteLeg`/`RoutePoint` (`App\Routing\Result\`) sont des DTO indépendants de tout
fournisseur — aucun type ni message d'erreur spécifique à Google ne fuit hors de
`GoogleRoutesProvider`. Deux implémentations existent : `GoogleRoutesProvider` (réelle) et
`FakeRoutingProvider` (déterministe, utilisée exclusivement en environnement de test via l'alias
`when@test` de `config/services.yaml`).

### Contrat Google Routes API v2

- Endpoint : `POST https://routes.googleapis.com/directions/v2:computeRoutes`.
- En-têtes obligatoires : `X-Goog-Api-Key` (clé, jamais journalisée) et `X-Goog-FieldMask` — sans
  ce dernier, l'API renvoie une erreur. Masque retenu :
  ```
  routes.distanceMeters,routes.duration,routes.polyline.geoJsonLinestring,
  routes.legs.distanceMeters,routes.legs.duration,routes.legs.startLocation,routes.legs.endLocation
  ```
  `legs.startLocation`/`endLocation` sont inclus spécifiquement pour obtenir les coordonnées
  résolues de chaque waypoint (y compris une adresse littérale) sans appel supplémentaire à une
  API de géocodage — utilisées pour générer les `<wpt>` du GPX.
- `polylineEncoding: "GEO_JSON_LINESTRING"` est demandé explicitement plutôt que le format par
  défaut (encoded polyline). **Justification** : le format encodé de Google nécessite un
  décodeur binaire maison (algorithme non trivial, risque réel d'erreurs d'arrondi/précision) ;
  demander directement une géométrie GeoJSON se décode avec `json_decode()` natif, sans
  dépendance ni code à tester en plus.
- Chaque waypoint (`origin`, `destination`, chaque entrée de `intermediates`) est un objet
  encapsulant soit `{"address": "..."}` soit `{"location": {"latLng": {...}}}` — jamais les deux
  — voir la règle de non-régression Address/Coordinates ci-dessous.
- `travelMode` est un champ de premier niveau de la requête (`DRIVE`, `WALK`, `BICYCLE`,
  `TWO_WHEELER`, `TRANSIT`), pas par segment.

### Mapping Address / Coordinates — règle de non-régression critique

`App\Routing\ValueObject\RouteLocationParser::parse()` est l'unique point d'entrée qui décide
si une chaîne représente des coordonnées GPS ou une adresse littérale :

```php
Coordinates::tryParse($raw) ?? Address::fromString($raw);
```

`Coordinates::tryParse()` n'accepte que les chaînes de la forme `lat,lng` dont les deux valeurs
sont dans les plages valides (`-90..90`, `-180..180`) ; toute autre chaîne — y compris une
chaîne de coordonnées hors plage — retombe sur `Address`. Testé explicitement (voir
`tests/Routing/ValueObject/RouteLocationParserTest.php`) contre les cas exigés par le brief
produit, dont la chaîne exacte `49.051624,2.0093594`, qui ne doit jamais être envoyée à Google
comme `{"address": "49.051624,2.0093594"}`.

### Gestion des erreurs

- Erreur de transport (timeout, DNS, connexion) ou statut HTTP ≥ 400 → journalisé en détail côté
  serveur, exposé à l'appelant comme `RoutingProviderUnavailableException` (message générique —
  ce n'est jamais une erreur de saisie de l'utilisateur).
- Réponse 200 avec un tableau `routes` vide → `RouteNotFoundException` (cas utilisateur légitime :
  aucun itinéraire routier entre les points donnés).
- JSON malformé ou champs attendus manquants → `RoutingProviderUnavailableException`.
- Timeouts courts (`timeout: 5`, `max_duration: 15`, client HTTP nommé `google.routes.client`,
  `config/packages/http_client.yaml`) pour qu'une réponse lente de Google ne bloque pas
  indéfiniment un worker PHP-FPM.

## Vérification effectuée

Testé contre l'API réelle (clé fournie en développement) : itinéraire Cergy → Paris, 35,6 km,
~61 minutes, 300 points de trace récupérés et convertis en GPX valide de bout en bout — voir
`documentation/technique/google-maps-to-gpx.md`.

## Alternatives envisagées

Le brief prévoit explicitement que `RoutingProviderInterface` permette de remplacer Google par
OpenRouteService, GraphHopper, OSRM ou Valhalla sans réécrire le moteur de conversion. Ces
fournisseurs ne sont pas retenus au lancement (Google Routes est déjà choisi par le produit),
mais **différés, pas rejetés** : rien dans la conception de `RouteLocation`/`RouteResult` ne
suppose Google, ce qui rend un changement de fournisseur ultérieur une simple nouvelle
implémentation de l'interface.
