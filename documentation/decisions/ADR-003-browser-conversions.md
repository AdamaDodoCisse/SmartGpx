# ADR-003 — Conversions gratuites exécutées côté navigateur

## Statut

Acceptée (Phase 1).

## Contexte

SmartGPX propose deux familles de fonctionnalités :

- la conversion payante **Google Maps → GPX**, qui nécessite un appel à un fournisseur de
  routing externe (Google Routes) et doit donc transiter par le backend Symfony, seul dépositaire
  des identifiants d'API ;
- une large famille d'outils **gratuits** de conversion/format (GPX ↔ KML, KMZ, TCX, FIT,
  GeoJSON, simplification, fusion, visionneuse) qui ne nécessitent aucun routing externe : ce
  sont de pures transformations de données déjà fournies par l'utilisateur.

## Décision

Tous les outils gratuits qui n'ont pas besoin d'un appel réseau externe s'exécutent
**entièrement dans le navigateur**, en TypeScript, sous `assets/app/src/gps/`. Le fichier
choisi par l'utilisateur n'est jamais envoyé au serveur Symfony pour ces outils.

Cette décision structure directement l'architecture :

- `assets/app/src/gps/{model,gpx,kml,kmz,tcx,fit,geojson,simplify,merge}/` contient la logique
  de parsing/génération, avec `model/` comme forme interne pivot (voir
  `documentation/technique/frontend.md`) ;
- aucune route Symfony n'existe pour uploader un GPX/KML/... à des fins de conversion de format ;
- seule la conversion payante Google Maps → GPX (Phase 2) et son homologue **GPX → Google Maps**
  (qui nécessite de générer une URL Google Maps, sans appel API tiers) suivent des logiques
  différentes — GPX → Google Maps reste néanmoins côté navigateur puisqu'aucune clé secrète n'est
  nécessaire pour construire une URL `google.com/maps/dir/...`.

## Conséquences

- **Confidentialité** : les traces GPS de l'utilisateur ne quittent jamais son navigateur pour
  ces outils — argument produit explicite (« Files stay on your device »).
- **Coût** : zéro calcul serveur pour la quasi-totalité du trafic « outils gratuits », ce qui
  est déterminant pour un produit dont l'acquisition repose sur ces outils.
- **Contrainte technique** : les bibliothèques de parsing (FIT notamment) doivent être
  compatibles navigateur (pas de dépendances Node-only) ; documenté au cas par cas dans
  `documentation/technique/{gpx,kml-kmz,tcx,fit,geojson}.md` au fur et à mesure de leur
  implémentation (Phase 5/6).
- **KMZ** : étant une archive ZIP, son extraction (protection zip bomb / path traversal) doit
  elle aussi être gérée en JavaScript côté navigateur — voir le stub
  `documentation/technique/kml-kmz.md`.

## Alternatives envisagées

- Tout faire transiter par le backend (upload systématique) : rejeté — coût serveur et surface
  d'attaque inutiles pour de simples transformations de fichiers déjà en possession de
  l'utilisateur.
