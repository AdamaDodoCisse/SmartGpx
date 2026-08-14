# Guides

**Statut : implémenté (Phase 7).**

## Principe

Huit pages de contenu SEO, statiques et server-rendues (voir
[ADR-004](../decisions/ADR-004-seo-rendering.md)), qui alimentent le moteur d'acquisition défini
dans `vision-produit.md` (« outils gratuits + SEO »). Chaque guide se termine par un ou plusieurs
liens internes vers l'outil gratuit ou le convertisseur payant concerné — c'est leur seule raison
d'être : capter une recherche longue traîne et rediriger vers un outil.

Aucun îlot React : contrairement aux pages `/tools/*`, les guides n'ont aucune interactivité,
seulement de la prose. Le contenu (EN/FR) vit directement dans le gabarit Twig de chaque guide,
branché sur `app.request.locale` — voir `documentation/technique/seo.md` pour le détail de ce
choix.

## Liste

| Guide | Route | Lien(s) vers |
|---|---|---|
| GPX vs KML | `/guides/gpx-vs-kml` | KML → GPX, GPX → KML |
| GPX vs TCX | `/guides/gpx-vs-tcx` | TCX → GPX, GPX → TCX |
| GPX vs FIT | `/guides/gpx-vs-fit` | FIT → GPX, GPX → FIT |
| GPX vs GeoJSON | `/guides/gpx-vs-geojson` | GeoJSON → GPX, GPX → GeoJSON |
| Convertir un itinéraire Google Maps en GPX | `/guides/google-maps-to-gpx` | Accueil (convertisseur), GPX → Google Maps |
| Qu'est-ce qu'un fichier KMZ ? | `/guides/what-is-kmz` | KMZ → GPX |
| Simplifier une trace GPS | `/guides/simplify-gps-track` | GPX Simplify |
| Fusionner des traces GPX | `/guides/merge-gpx-tracks` | GPX Merge |

Une page d'index (`/guides`, `/fr/guides`) liste les huit guides, sur le même principe de carte
que la grille d'outils de la page d'accueil.

## Navigation

Les liens « Tools » et « Guides » de l'en-tête (desktop et menu mobile) n'existaient pas avant
cette phase — ajoutés en même temps que les guides, puisqu'il n'y avait auparavant aucun moyen
d'atteindre les pages d'outils ou de guides depuis la navigation principale.
