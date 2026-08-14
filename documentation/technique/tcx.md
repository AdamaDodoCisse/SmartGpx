# TCX

**Statut : implémenté (Phase 6), vérifié par tests unitaires.**

`assets/app/src/gps/tcx/index.ts`, `DOMParser`/`XMLSerializer` natifs — mêmes helpers partagés
que `gpx/`/`kml/` (`assets/app/src/gps/shared/xml.ts`), aucune dépendance externe.

## Mapping

| Élément TCX | Champ pivot |
|---|---|
| `<Activities><Activity>` | un `GpsTrack` |
| `<Activity Sport="...">` | `GpsTrack.name` |
| `<Lap><Track><Trackpoint>` | `GpsTrack.points[]` |
| `<Trackpoint><Position><LatitudeDegrees>`/`<LongitudeDegrees>` | `latitude`/`longitude` |
| `<Trackpoint><AltitudeMeters>` | `elevation` |
| `<Trackpoint><Time>` | `time` |

**Aucun équivalent waypoint/route.** TCX est un format d'activité sportive (course, vélo…), pas
un format de planification d'itinéraire : `parseTcx` renvoie toujours `waypoints: []` et
`routes: []`. Symétriquement, `generateTcx` ignore silencieusement `route.waypoints` s'il y en a
— perte **documentée et volontaire**, même précédent déjà posé pour la fusion `<trk>`/`<rte>` de
KML (voir `kml-kmz.md`).

**Aplatissement des laps.** Une activité TCX découpe ses points en plusieurs `<Lap>`, chacun
contenant un ou plusieurs `<Track>`. Aucun outil en aval de SmartGPX n'a besoin de connaître ces
frontières : `parseTcx` concatène tous les `<Trackpoint>` d'une activité en une seule liste de
points, exactement comme `parseGpx` aplatit déjà les `<trkseg>` d'une trace GPX. Plusieurs
`<Activity>` dans un même fichier produisent plusieurs `GpsTrack` distincts (une par activité),
et non un unique aplatissement global.

Un `<Trackpoint>` sans `<Position>` (fréquent sur les capteurs de fréquence cardiaque/cadence
seuls, entre deux points GPS) est ignoré plutôt que de faire échouer tout le parsing.

`generateTcx` écrit `<Id>` (obligatoire dans le schéma TCX) à partir de l'horodatage du premier
point de la trace.

Testé (`tcx/index.test.ts`) : fidélité aller-retour, aplatissement multi-lap, fichier
multi-activité → plusieurs traces, point sans position ignoré, fichier vide ou XML malformé lève
une erreur claire, waypoints silencieusement absents en sortie sans exception.
