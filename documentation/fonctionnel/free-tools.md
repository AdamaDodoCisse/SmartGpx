# Outils gratuits

**Statut : listés sur la page d'accueil depuis la Phase 1 ; implémentation réelle en Phase 5
(GPX Viewer, GPX → Google Maps, GPX Simplify, GPX Merge, KML ↔ GPX) et Phase 6 (KMZ → GPX, TCX ↔
GPX, FIT ↔ GPX, GeoJSON ↔ GPX).**

## Principe commun

Aucun de ces outils ne consomme de crédit. Ils s'exécutent côté navigateur dès que c'est
techniquement raisonnable (voir [ADR-003](../decisions/ADR-003-browser-conversions.md)) — le
fichier de l'utilisateur ne quitte pas son appareil. Message affiché systématiquement pour ces
outils : « Files stay on your device. »

## Liste

| Outil | Route prévue | Entrée | Sortie |
|---|---|---|---|
| GPX Viewer | `/gpx-viewer` | GPX | carte interactive |
| GPX → Google Maps | `/tools/gpx-to-google-maps` | GPX | lien Google Maps |
| GPX Simplify | `/tools/gpx-simplify` | GPX | GPX simplifié (Ramer-Douglas-Peucker) |
| GPX Merge | `/tools/gpx-merge` | plusieurs GPX | GPX fusionné |
| KML → GPX | `/tools/kml-to-gpx` | KML | GPX |
| GPX → KML | `/tools/gpx-to-kml` | GPX | KML |
| KMZ → GPX | `/tools/kmz-to-gpx` | KMZ (zip de KML) | GPX |
| TCX → GPX | `/tools/tcx-to-gpx` | TCX | GPX |
| GPX → TCX | `/tools/gpx-to-tcx` | GPX | TCX |
| FIT → GPX | `/tools/fit-to-gpx` | FIT | GPX |
| GPX → FIT | `/tools/gpx-to-fit` | GPX | FIT (profil course) |
| GeoJSON → GPX | `/tools/geojson-to-gpx` | GeoJSON | GPX |
| GPX → GeoJSON | `/tools/gpx-to-geojson` | GPX | GeoJSON |

## GPX Simplify — transparence

La simplification d'une trace dense implique une perte d'information ; l'outil doit toujours
afficher le nombre de points avant/après et le taux de réduction, et rester honnête sur le fait
que la trajectoire de navigation résultante peut différer légèrement de la trace d'origine.

## KMZ — sécurité

Une archive KMZ est un ZIP contenant un KML. L'extraction doit se prémunir contre les zip bombs,
le path traversal et les archives imbriquées inattendues — voir
`documentation/technique/kml-kmz.md` (à rédiger en Phase 6).
