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
seconde source de vérité. Le contrôleur exclut les préfixes non publics (`/account/`, `/api/`,
`/billing/`, `/reset-password`, `/register`, `/verify/`, `/logout`) et pointe `Sitemap:` vers
l'URL absolue générée du sitemap.

## Canonical / hreflang

Dans `templates/base.html.twig`, juste après le bloc `meta_description` :

```twig
{% set canonical_route = app.request.attributes.get('_canonical_route', app.request.attributes.get('_route')) %}
<link rel="canonical" href="{{ url(canonical_route) }}">
<link rel="alternate" hreflang="en" href="{{ url(canonical_route, {_locale: 'en'}) }}">
<link rel="alternate" hreflang="fr" href="{{ url(canonical_route, {_locale: 'fr'}) }}">
<link rel="alternate" hreflang="x-default" href="{{ url(canonical_route, {_locale: 'en'}) }}">
```

Le raccourci de routage internationalisé (`#[Route(['en' => ..., 'fr' => ...])]`, utilisé partout
dans l'application depuis la Phase 1) enregistre en réalité deux routes physiques par attribut
(`nom.en`, `nom.fr`), chacune portant un défaut `_canonical_route` égal au nom canonique — c'est
ce que `RouterListener` expose comme attribut de requête (`_canonical_route`), lisible depuis
n'importe quel template. `url($nom, {_locale: 'xx'})` résout alors la route physique de la locale
demandée. C'est ce mécanisme qui permet un bloc canonical/hreflang générique dans `base.html.twig`
sans dupliquer de logique dans chaque page — vérifié en lisant directement les sources de
`vendor/symfony/routing` (`AttributeClassLoader`, `RouterListener`, `UrlGenerator`), pas supposé.

Un guide (`app.request.attributes.get('_canonical_route')` absent) retombe sur `_route` — filet de
sécurité bon marché plutôt qu'une garantie testée sur toutes les routes existantes.
