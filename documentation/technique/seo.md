# SEO : sitemap, robots.txt, canonical/hreflang

**Statut : implémenté (Phase 7).**

Complète [ADR-004](../decisions/ADR-004-seo-rendering.md) (rendu Twig côté serveur) avec
l'infrastructure de découverte/indexation qui manquait entièrement avant cette phase : aucun
sitemap, aucun robots.txt, aucune balise canonical/hreflang n'existait.

## Sitemap (`/sitemap.xml`)

`src/Controller/SitemapController.php` — aucun indicateur natif Symfony ne distingue les routes
publiques des routes privées (auth, API, admin…), donc la liste des routes publiques
(`PUBLIC_ROUTES`) est tenue à la main, comme `ToolsControllerTest::routes()` ou la carte d'outils
de la page d'accueil. Chaque route est résolue en URL absolue pour les deux locales via
`UrlGeneratorInterface::generate($route, ['_locale' => $locale], ABSOLUTE_URL)`, puis rendue par
`templates/sitemap/sitemap.xml.twig` — un template comme les autres plutôt qu'une chaîne XML
construite à la main, ce qui le rend testable de la même façon que n'importe quelle page.

L'échappement HTML par défaut de Twig pour les fichiers `.xml.twig` est un sur-ensemble sûr pour
du texte d'URL brut — aucun échappeur personnalisé n'est nécessaire.

## Robots (`/robots.txt`)

`src/Controller/RobotsController.php` — un contrôleur plutôt qu'un fichier statique dans
`public/`. `/robots.txt` est toujours servi via une vraie requête HTTP, contexte où
`UrlGeneratorInterface` résout déjà le bon hôte absolu sans configuration supplémentaire ; un
fichier statique obligerait soit à coder en dur un domaine, soit à dupliquer `DEFAULT_URI` comme
seconde source de vérité. Le contrôleur exclut les préfixes non publics (`/admin`, `/account/`,
`/api/`, `/billing/`, `/reset-password`, `/register`, `/verify/`, `/logout`) et pointe `Sitemap:`
vers l'URL absolue générée du sitemap. `/admin` (back-office Phase 8) a été ajouté après coup —
la liste n'est pas régénérée automatiquement à partir des routes de l'application, donc toute
nouvelle section privée doit y être ajoutée à la main.

## Canonical / hreflang

**Régression corrigée après la Phase 8** (voir le commit « Fix canonical/hreflang tags missing
site-wide since Phase 8 ») : le bloc ci-dessous décrit le mécanisme réel, vérifié en requêtant le
serveur directement (un commentaire `<!-- DEBUG -->` temporaire dans `base.html.twig` affichant
les attributs de requête réels) plutôt qu'en lisant seulement le code source — la première version
de cette section décrivait un mécanisme plausible mais faux, resté invisible pendant plusieurs
commits faute de test.

Dans `templates/base.html.twig`, juste après le bloc `meta_description` :

```twig
{% set canonical_route = app.request.attributes.get('_route') %}
{% if canonical_route and app.request.attributes.get('_locale') %}
    {% set route_params = app.request.attributes.get('_route_params', {}) %}
    <link rel="canonical" href="{{ url(canonical_route, route_params) }}">
    <link rel="alternate" hreflang="en" href="{{ url(canonical_route, route_params|merge({_locale: 'en'})) }}">
    <link rel="alternate" hreflang="fr" href="{{ url(canonical_route, route_params|merge({_locale: 'fr'})) }}">
    <link rel="alternate" hreflang="x-default" href="{{ url(canonical_route, route_params|merge({_locale: 'en'})) }}">
{% endif %}
```

Le raccourci de routage internationalisé (`#[Route(['en' => ..., 'fr' => ...])]`, utilisé partout
dans l'application depuis la Phase 1) enregistre en réalité deux routes physiques par attribut
(`nom.en`, `nom.fr`). Chaque route porte bien un défaut `_canonical_route` égal au nom canonique —
visible via `bin/console debug:router nom.en` — **mais cet attribut n'est jamais exposé sur la
requête au runtime**, contrairement à ce qu'on pourrait attendre d'un simple défaut de route.
Ce qui est réellement exposé : `_route`, déjà normalisé au nom canonique (sans suffixe `.en`/`.fr`)
une fois la route matchée, et `_locale`, présent uniquement sur les routes i18n (absent sur les
routes internes à chemin unique comme `/admin/*`, qui ne portent pas ce défaut). C'est donc la
présence de `_locale` qui distingue une page publique i18n d'une route interne — pas la présence
de `_canonical_route`, qui vaut toujours `null` en pratique. `url($nom, {_locale: 'xx'})` résout
ensuite la route physique de la locale demandée.

Une route interne non i18n (`_locale` absent) n'affiche aucune balise — c'est le comportement
voulu pour `/admin/*`, qui n'a pas d'équivalent `.fr`.
