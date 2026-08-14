# KML / KMZ

**Statut : KML et KMZ implémentés (Phase 5 / Phase 6), vérifiés par tests unitaires.**

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

`assets/app/src/gps/kmz/index.ts`, `fflate` (`unzipSync`) — seule dépendance externe du module
`gps/` en dehors de `@garmin/fitsdk` (voir `fit.md`) : décompresser un ZIP en toute sécurité
n'est pas quelque chose à réimplémenter soi-même. **Sens unique** : KMZ → GPX seulement, aucun
`generateKmz` — aucune route `/tools/gpx-to-kmz` n'existe dans le produit.

### Garde-fous de sécurité

Le filtre `unzipSync({ filter })` est appelé **avant** décompression de chaque entrée (la taille
non compressée déclarée, `originalSize`, vient de l'en-tête ZIP) :

- **Zip bomb** : toute entrée dont `originalSize` dépasse 50 Mo est rejetée sans jamais être
  inflatée. Un KML de cette taille n'existe pas en pratique (les exports réels de Google Earth /
  Google My Maps font quelques centaines de Ko à quelques Mo).
- **Path traversal** : toute entrée dont le nom commence par `/` ou contient un segment `..` est
  rejetée. Défense en profondeur plutôt que protection contre une vulnérabilité active : les
  octets extraits ne sont jamais écrits sur un chemin disque (tout reste en mémoire, transmis
  directement à `parseKml`) — mais un nom d'entrée d'archive n'est jamais une donnée de confiance.
- **Archives imbriquées** : toute entrée `.zip`/`.kmz` est ignorée (jamais décompressée
  récursivement) plutôt que traitée — évite un vecteur de zip bomb récursif sans complexité
  supplémentaire.

### Quel KML choisir ?

Une archive KMZ réelle contient quasi universellement un unique `doc.kml` à sa racine (convention
Google Earth / Google My Maps). `parseKmz` prend donc la première entrée `.kml` trouvée (la plus
proche de la racine, puis par ordre alphabétique en cas d'égalité) plutôt que d'exiger un nom
exact — une archive sans aucune entrée `.kml` exploitable lève une erreur claire.

Testé (`kmz/index.test.ts`) : extraction transparente (équivalente à appeler `parseKml`
directement sur le KML extrait), rejet d'une entrée surdimensionnée, rejet d'un nom de chemin
dangereux, archive imbriquée ignorée sans bloquer l'extraction du KML valide, archive sans KML.
