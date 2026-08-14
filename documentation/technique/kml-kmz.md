# KML / KMZ

**Statut : KML implémenté (Phase 5), vérifié par tests unitaires. KMZ non implémenté (Phase 6).**

## KML

`assets/app/src/gps/kml/index.ts`, `DOMParser`/`XMLSerializer` natifs (mêmes helpers partagés que
`gpx/`, voir `assets/app/src/gps/shared/xml.ts`).

Mapping :

| Élément KML | Champ pivot |
|---|---|
| `<Placemark><Point><coordinates>` | `GpsRoute.waypoints[]` |
| `<Placemark><LineString><coordinates>` | `GpsRoute.tracks[]` |
| `<Placemark><name>`/`<description>` | `name`/`description` du waypoint ou de la trace |

**L'ordre des coordonnées KML est `lon,lat[,ele]`** (séparées par des virgules au sein d'un
point, par des espaces entre points) — **inverse** des champs nommés `GpsPoint.latitude`/
`longitude`. Même piège que GeoJSON, déjà rencontré côté serveur dans
`App\Routing\Provider\GoogleRoutesProvider` (Phase 2) ; testé explicitement
(`kml/index.test.ts`) pour ne jamais régresser silencieusement.

KML n'a **aucun équivalent** de la distinction GPX `<trk>`/`<rte>` — `generateKml` fusionne
`tracks` et `routes` dans des `<Placemark><LineString>`. Fusion **documentée et volontaire**, pas
une perte accidentelle : un GPX avec à la fois des `<trk>` et des `<rte>`, converti en KML puis
reconverti en GPX, revient avec tout sous forme de `<trk>` — comportement inhérent au modèle de
données KML, pas un défaut de SmartGPX.

## KMZ

<!-- KMZ : Phase 6. Extraction ZIP en JavaScript navigateur, protection zip bomb / path
traversal / archives imbriquées inattendues avant de déléguer au parseur KML ci-dessus. -->
