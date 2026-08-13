# ADR-004 — Rendu SEO : Twig côté serveur + îlots React

## Statut

Acceptée et vérifiée en fonctionnement (Phase 1).

## Contexte

Le SEO est un canal de distribution central pour SmartGPX (page d'accueil, `/pricing`, futures
pages `/tools/*`, guides). Le contenu public critique doit être visible dans le HTML initial —
pas de coquille SPA vide. Dans le même temps, la stack retenue pour l'interactivité est
React + Vite (pas un framework à SSR intégré type Next.js), et le produit contient de vrais
widgets interactifs (le convertisseur Maps → GPX, les outils de conversion de fichiers).

## Décision

- **Symfony + Twig rendent toutes les pages publiques côté serveur** (accueil, pricing, pages
  légales, futures pages SEO/outils) : HTML complet dès la première réponse, entièrement
  crawlable, sans dépendre de JavaScript pour le contenu principal.
- **React (Vite) est utilisé en « îlots »** : des composants montés dans des `<div>` précis du
  HTML rendu par Twig, pour les éléments réellement interactifs (menu mobile en Phase 1 ; futurs
  convertisseurs, visionneuse de carte, etc.). Le reste du DOM reste du Twig statique.
- Intégration technique via **`pentatrion/vite-bundle`** plutôt qu'un lecteur de manifest
  Vite écrit à la main : le bundle gère correctement la bascule dev/prod, l'injection du
  préambule React Fast Refresh, et le format `entrypoints.json` — des détails faciles à mal
  reproduire à la main, pour un bénéfice nul face à un outil largement utilisé et maintenu.
- **Aucun runtime Node en production** : `npm run build` produit des fichiers statiques dans
  `public/build/`, lus par les fonctions Twig du bundle ; en développement, `npm run dev` lance
  un serveur Vite (HMR) que le bundle détecte automatiquement pour servir les modules en direct.

## Mécanique

- `assets/app/vite.config.ts` : projet Vite racine, sortie vers `../../public/build` (donc
  `public/build` à la racine du projet Symfony), `base: '/build/'` (valeur par défaut du bundle).
- `templates/base.html.twig` :
  - `{{ vite_entry_link_tags('app') }}` dans `<head>` (CSS Tailwind) ;
  - `{{ vite_entry_script_tags('nav', { dependency: 'react' }) }}` en fin de `<body>`, qui monte
    le composant `MobileMenu` dans `<div id="nav-mobile-menu-root">`.
- Chaque futur îlot suit le même schéma : un fichier d'entrée dans `assets/app/src/entries/`,
  un `<div id="...">` dans le template Twig concerné, un appel à `mountIsland()`
  (`assets/app/src/lib/mountIsland.tsx`).
- Répartition statique/interactif : la barre de navigation desktop est du Twig pur (crawlable,
  zéro JS requis) ; seul le menu mobile (état ouvert/fermé, piège de focus) est un îlot React,
  car cet état n'a aucune valeur SEO et bénéficie des primitives accessibles de Radix (via
  shadcn/ui).

## Vérification effectuée (Phase 1)

- Build de production (`npm run build`) : génère `public/build/.vite/manifest.json` et
  `entrypoints.json`, lus correctement par les fonctions Twig (page rendue avec les bons tags
  `<link>`/`<script>`, sans serveur Node actif).
- Mode développement (`npm run dev`) : le bundle détecte le serveur Vite actif et sert les
  modules depuis `http://[::1]:5173/build/...` avec injection du client `@vite/client` (HMR) —
  vérifié par requête HTTP directe sur les deux modes.
- Retour automatique aux assets buildés dès l'arrêt du serveur Vite dev (après un nouveau
  `npm run build`, le manifest régénéré prime).

## Conséquences

- Un seul processus à faire tourner en production (PHP/Symfony) — pas de service Node à
  opérer/superviser.
- Toute nouvelle page publique doit être un contrôleur Twig, jamais une route purement API
  consommée par une SPA cliente.
- Les futurs outils interactifs (Phase 5/6) suivront le schéma îlot : contenu/structure/FAQ en
  Twig (SEO), widget de conversion en React.

## Alternatives envisagées

- **SPA React entièrement pré-rendue au build** (ex. vite-plugin-ssg) : rejetée — retire Twig du
  jeu alors que le reste du produit (auth, futurs formulaires serveur) en dépend déjà, et
  complique le pré-rendu de pages dont le contenu dépendra de données dynamiques (tarifs,
  compteurs de crédits).
- **SPA React + SSR Node en production** : rejetée — ajoute un second runtime à déployer et
  superviser, contraire à la consigne de ne pas sur-construire l'infrastructure en Phase 1.
