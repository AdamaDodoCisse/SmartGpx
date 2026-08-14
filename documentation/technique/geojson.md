# GeoJSON

**Statut : implémenté (Phase 6), vérifié par tests unitaires.**

`assets/app/src/gps/geojson/index.ts`, `JSON.parse`/`JSON.stringify` natifs — GeoJSON est déjà du
JSON, aucune bibliothèque n'est nécessaire.

## Normalisation d'enveloppe

Contrairement à GPX/KML/TCX, GeoJSON n'a pas une seule forme de document valide : un fichier réel
peut être une `FeatureCollection`, une `Feature` isolée, ou même une géométrie nue sans
enveloppe `Feature`. `parseGeoJson` normalise les trois formes vers une même liste de features
avant de les traiter — les exports réels (Google My Maps, geojson.io, QGIS…) sont presque
toujours des `FeatureCollection`, mais les deux autres formes sont légales et rencontrées.

## Mapping géométrie → modèle pivot

| Géométrie GeoJSON | Champ pivot |
|---|---|
| `Point` | `GpsRoute.waypoints[]` |
| `LineString` | `GpsRoute.tracks[]` (une trace) |
| `MultiLineString` | plusieurs `GpsRoute.tracks[]` — une trace par sous-ligne, frontières préservées |
| `properties.name`/`description` | `name`/`description` du waypoint ou de la trace |
| `Polygon`, `MultiPoint`, `MultiPolygon`, `GeometryCollection` | ignorés silencieusement — aucune correspondance naturelle avec `GpsRoute` |

`generateGeoJson` émet toujours une `FeatureCollection` (jamais une `Feature`/géométrie nue en
sortie), quelle que soit la forme du fichier d'entrée.

**L'ordre des coordonnées GeoJSON est `[longitude, latitude, élévation?]`** (RFC 7946) —
**inverse** des champs nommés `GpsPoint.latitude`/`longitude`. Même piège, même vigilance que KML
(voir `kml-kmz.md`) et déjà rencontré côté serveur dans `App\Routing\Provider\GoogleRoutesProvider`
(Phase 2) ; testé explicitement (`geojson/index.test.ts`) pour ne jamais régresser silencieusement.

Testé (`geojson/index.test.ts`) : `FeatureCollection`/`Feature` isolée/géométrie nue parsent de
façon équivalente, ordre des coordonnées non inversé par erreur, `MultiLineString` éclate bien en
plusieurs traces distinctes, géométries non supportées ignorées sans lever d'erreur (sauf si rien
d'exploitable n'est trouvé au final), fidélité aller-retour, sortie toujours une
`FeatureCollection`.
