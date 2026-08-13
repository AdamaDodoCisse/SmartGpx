# Frontend

## Stack

Vite + React + TypeScript + Tailwind CSS v4 (`@tailwindcss/vite`, configuration CSS-first, pas
de `tailwind.config.ts`) + shadcn/ui (primitives Radix, voir `components.json`) + Lucide React.

## Où vit le code

Tout le frontend applicatif vit sous `assets/app/` (racine du projet Vite : `package.json`,
`vite.config.ts` s'y trouvent, pas à la racine du dépôt) :

```
assets/app/src/
  entries/         # points d'entrée Vite (un fichier = un îlot ou l'entrée CSS globale)
  components/ui/    # primitives shadcn/ui (Button, Sheet, ...)
  components/layout/ # composants de mise en page (ex. MobileMenu)
  lib/             # utils.ts (cn()), mountIsland.tsx
  i18n/            # react-i18next
  gps/             # moteur de conversion partagé (voir ci-dessous)
```

## Îlots React

Voir [ADR-004](../decisions/ADR-004-seo-rendering.md) pour la justification architecturale. En
pratique, ajouter un îlot suit toujours le même schéma :

1. un fichier d'entrée dans `assets/app/src/entries/mon-ilot.tsx` ;
2. l'ajouter à `build.rollupOptions.input` dans `vite.config.ts` ;
3. un `<div id="mon-ilot-root" data-...="...">` dans le template Twig concerné ;
4. `{{ vite_entry_script_tags('mon-ilot', { dependency: 'react' }) }}` dans ce template ;
5. dans `mon-ilot.tsx` : `mountIsland('mon-ilot-root', (props) => <MonComposant {...props} />)`.

`mountIsland()` (`src/lib/mountIsland.tsx`) lit les attributs `data-*` du noeud DOM et les passe
en props — c'est le seul canal de communication Twig → React (pas de variables globales).

## Workflow de développement

Deux processus en parallèle :

```
symfony serve         # ou: php -S 127.0.0.1:8000 -t public
cd assets/app && npm run dev   # serveur Vite (HMR), détecté automatiquement par le bundle PHP
```

En production, `npm run build` (exécuté dans `assets/app/`) suffit — aucun processus Node ne
tourne au runtime.

## Internationalisation (i18n)

Deux catalogues distincts, tenus manuellement en parité (pas de synchronisation automatique en
Phase 1 — la surface de traduction est encore restreinte) :

- **Twig** : `translations/messages.{en,fr}.yaml` (`symfony/translation`).
- **React** : `assets/app/src/i18n/locales/{en,fr}/common.json` (`react-i18next`).

La locale initiale est transmise par Twig via `data-locale` sur le point de montage et lue par
`mountIsland`/`i18n/index.ts` au chargement — un seul bundle JS compilé sert les deux langues,
sans duplication.

## Moteur de conversion partagé (`gps/`)

Prévu par le brief produit (§29) pour éviter de dupliquer le parsing dans chaque page React.
`gps/model/` définit la forme interne pivot (`GpsRoute`, `GpsTrack`, `GpsWaypoint`, `GpsPoint`) ;
chaque format (`gpx/`, `kml/`, `kmz/`, `tcx/`, `fit/`, `geojson/`) convertit depuis/vers cette
forme, et `simplify/`/`merge/` opèrent dessus. **En Phase 1, ces modules sont des stubs typés qui
lèvent une erreur** — l'implémentation réelle arrive en Phase 5 (GPX, KML, simplify, merge) et
Phase 6 (KMZ, TCX, FIT, GeoJSON), voir [ADR-003](../decisions/ADR-003-browser-conversions.md).
Les stubs existent dès maintenant pour que les futurs modules partagent un seul modèle de
données dès le premier commit qui les implémente, plutôt que de redécouvrir la forme commune
plus tard.
