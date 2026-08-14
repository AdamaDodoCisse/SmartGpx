# GPX

**Statut : implémenté (Phase 5), vérifié par tests unitaires (fidélité aller-retour).**

## Où c'est

```
assets/app/src/gps/gpx/index.ts       # parseGpx / generateGpx
assets/app/src/gps/shared/xml.ts       # helpers DOM partagés avec kml/
```

## Parsing/génération

`DOMParser`/`XMLSerializer` natifs du navigateur — pas de bibliothèque XML (voir
[ADR-003](../decisions/ADR-003-browser-conversions.md)). Mapping GPX 1.1 :

| Élément GPX | Champ `GpsRoute`/`GpsTrack`/`GpsWaypoint`/`GpsPoint` |
|---|---|
| `<metadata><name>` | `GpsRoute.name` |
| `<wpt lat lon>` + `<ele>`/`<time>`/`<name>`/`<desc>` | `GpsRoute.waypoints[]` |
| `<trk>` — tous les `<trkseg><trkpt>` concaténés en un seul tableau de points | `GpsRoute.tracks[]` |
| `<rte><rtept>` | `GpsRoute.routes[]` |

`<trk>` et `<rte>` sont deux éléments GPX distincts ; le modèle pivot les représente tous deux
avec la forme `GpsTrack` (nom + liste de points) — structurellement identiques, une distinction
de type séparée n'aurait aucune valeur. Les segments `<trkseg>` d'un même `<trk>` sont aplatis
dans un seul `GpsTrack.points` — la frontière entre segments n'existe pas dans le modèle pivot
(perte assumée, aucun outil actuel n'en a besoin).

Un point individuel malformé (`lat`/`lon` non numériques) est ignoré silencieusement plutôt que
de faire échouer tout le fichier ; un fichier sans aucun waypoint/trace/itinéraire lève une
exception (« ce n'est pas un GPX »).

`generateGpx` reprend les mêmes conventions que le générateur PHP côté serveur
(`App\Conversion\Gpx\GpxGenerator`, Phase 2) : namespace `http://www.topografix.com/GPX/1/1`,
`version="1.1"`, `creator="SmartGPX"`, déclaration `<?xml version="1.0" encoding="UTF-8"?>` — pas
la mise en forme byte-à-byte (deux générateurs indépendants, aucun lecteur GPX ne se soucie des
espaces).

## GPX Simplify — Ramer-Douglas-Peucker

`assets/app/src/gps/simplify/index.ts`. La distance perpendiculaire utilisée par RDP est calculée
dans une **projection équirectangulaire locale** (mètres/degré de latitude ≈ 111 320, mètres/degré
de longitude mis à l'échelle par `cos(latitude)`) plutôt qu'une haversine complète — suffisamment
précis à l'échelle d'une trace GPS unique, bien plus simple qu'une bibliothèque géographique
complète.

`maxPoints` est un plafond dur appliqué par **décimation uniforme après RDP** (jamais une cible
que RDP viserait directement), en conservant toujours le premier et le dernier point — protège
contre une tolérance trop permissive combinée à un plafond strict, sans ré-exécuter RDP avec une
tolérance plus élevée.

## GPX Merge

`assets/app/src/gps/merge/index.ts`. `'single-track'` aplatit tous les points de toutes les
traces/itinéraires source en une seule `GpsTrack` continue ; `'separate-segments'` conserve
chaque trace/itinéraire source comme entrée distincte de `tracks[]`. Dans les deux cas, la sortie
n'a jamais de `routes` (la distinction trk/rte n'a aucune valeur produit une fois fusionnée dans
un seul GPX téléchargeable). Les waypoints sont concaténés tels quels, sans préfixage
anti-collision. Le nom résultant joint les noms sources non vides avec « + ».

## GPX Viewer

Leaflet + `react-leaflet` + tuiles raster OpenStreetMap, sans clé d'API — voir
`documentation/technique/frontend.md`. **Limite connue, non bloquante** : le serveur de tuiles
public d'OSM a une politique d'usage raisonnable ; si le trafic de l'outil grandit
significativement, remplacer l'URL de tuiles par un fournisseur payant (MapTiler, Stadia Maps,
...) est un changement d'une ligne (`<TileLayer url>`), pas un changement d'architecture.

## Tests

`assets/app/src/gps/gpx/index.test.ts`, `simplify/index.test.ts`, `merge/index.test.ts` — voir
`npm run test` dans `assets/app/`. Le test le plus significatif est la fidélité aller-retour
(`generateGpx(parseGpx(x))` reparsé égale la structure d'origine), plutôt que des assertions
champ par champ isolées.
