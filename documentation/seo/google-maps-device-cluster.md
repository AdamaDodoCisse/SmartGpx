# Cluster SEO : Google Maps → Garmin / Wahoo / OsmAnd

Phase 12 (non nommée dans le brief produit initial, comme les Phases 9-11) : trois nouvelles
pages de guide, à l'intérieur du système `/guides` existant, pour capter une intention de
recherche que rien sur le site ne couvrait avant cette phase : « comment utiliser mon itinéraire
Google Maps sur mon [appareil] », distincte de l'intention déjà couverte par
`/guides/google-maps-to-gpx` (« comment convertir »).

## Audit de cannibalisation

| Page | Intention principale |
|---|---|
| Page d'accueil | Conversion Google Maps → GPX / produit |
| `/guides/google-maps-to-gpx` | Comment convertir Google Maps en GPX (générique) |
| `/guides/google-maps-to-garmin` | Comment utiliser un itinéraire Google Maps avec Garmin |
| `/guides/google-maps-to-wahoo` | Comment utiliser un itinéraire Google Maps avec Wahoo |
| `/guides/google-maps-to-osmand` | Comment utiliser un itinéraire Google Maps avec OsmAnd |

Quatre intentions distinctes, confirmées par une relecture complète de `GuidesController` et des
9 gabarits de guides existants avant implémentation : aucune page existante ne répondait déjà à
l'intention « appareil spécifique ». Aucune page n'a donc été fusionnée ni retirée ; le guide
générique existant devient le centre du cluster (voir « Maillage interne » plus bas).

## Table de référence par page

### Garmin

| Champ | Valeur |
|---|---|
| URL (EN) | `/guides/google-maps-to-garmin` |
| URL (FR) | `/fr/guides/convertir-google-maps-en-garmin` |
| Mot-clé principal | `google maps to garmin` |
| Mots-clés secondaires | `google maps route to garmin`, `google maps to garmin connect`, `google maps gpx garmin`, `send google maps route to garmin`, `import google maps route to garmin` |
| Title | Google Maps to Garmin: Convert Your Route to GPX \| SmartGPX |
| H1 | How to Send a Google Maps Route to Garmin |
| Meta description | Convert a Google Maps route to GPX for Garmin. Paste your route into SmartGPX, download the GPX file, then import it as a course in Garmin Connect. |
| Canonical | Auto-canonical (route i18n standard, voir base.html.twig) — jamais vers la homepage ni le guide générique |
| Hreflang | en / fr / x-default, généré automatiquement |
| Maillage entrant | Hub `/guides`, guide `google-maps-to-gpx`, page d'accueil (badge de compatibilité + FAQ) |
| Maillage sortant | Guide `google-maps-to-gpx`, GPX Viewer, guides Wahoo et OsmAnd, extension Chrome (ancre) |
| CTA | Convert your route for Garmin |
| GTM `landing_page` | `guide_google_maps_garmin` |

### Wahoo

| Champ | Valeur |
|---|---|
| URL (EN) | `/guides/google-maps-to-wahoo` |
| URL (FR) | `/fr/guides/convertir-google-maps-en-wahoo` |
| Mot-clé principal | `google maps to wahoo` |
| Mots-clés secondaires | `google maps route to wahoo`, `google maps to wahoo elemnt`, `google maps to wahoo bolt`, `gpx wahoo`, `google maps gpx wahoo` |
| Title | Google Maps to Wahoo: Convert Your Route to GPX \| SmartGPX |
| H1 | How to Send a Google Maps Route to Wahoo |
| Meta description | Turn a Google Maps route into a GPX file for Wahoo. Paste your route into SmartGPX, download the GPX, then import it with the Wahoo app. |
| Canonical | Auto-canonical (idem) |
| Hreflang | en / fr / x-default, généré automatiquement |
| Maillage entrant | Hub `/guides`, guide `google-maps-to-gpx`, page d'accueil (badge de compatibilité + FAQ) |
| Maillage sortant | Guide `google-maps-to-gpx`, GPX Viewer, guides Garmin et OsmAnd, extension Chrome (ancre) |
| CTA | Convert your route for Wahoo |
| GTM `landing_page` | `guide_google_maps_wahoo` |

### OsmAnd

| Champ | Valeur |
|---|---|
| URL (EN) | `/guides/google-maps-to-osmand` |
| URL (FR) | `/fr/guides/convertir-google-maps-en-osmand` |
| Mot-clé principal | `google maps to osmand` |
| Mots-clés secondaires | `google maps route to osmand`, `import google maps route into osmand`, `google maps gpx osmand`, `gpx to osmand` |
| Title | Google Maps to OsmAnd: Convert Routes to GPX \| SmartGPX |
| H1 | How to Use a Google Maps Route in OsmAnd |
| Meta description | Convert your Google Maps route to GPX and use it with OsmAnd. Paste the route into SmartGPX, download the GPX file, then open it in OsmAnd. |
| Canonical | Auto-canonical (idem) |
| Hreflang | en / fr / x-default, généré automatiquement |
| Maillage entrant | Hub `/guides`, guide `google-maps-to-gpx`, page d'accueil (badge de compatibilité + FAQ) |
| Maillage sortant | Guide `google-maps-to-gpx`, GPX Viewer, guides Garmin et Wahoo, extension Chrome (ancre) |
| CTA | Convert your route for OsmAnd |
| GTM `landing_page` | `guide_google_maps_osmand` |

## Architecture technique (résumé — voir le code pour le détail)

- **Le convertisseur est réellement monté** sur ces 3 pages (îlot React `ConvertHero`, identique à
  la page d'accueil), pas un simple lien vers `/`. `src/Shared/ConvertHero/ConvertHeroPropsProvider.php`
  centralise le calcul du solde de crédits et des capabilities de routing, réutilisé par
  `HomeController` (refactor) et `GuidesController` (nouveau) plutôt que dupliqué.
- **Structured data** : `BreadcrumbList` (nouveau pattern, `templates/_partials/breadcrumb.html.twig`),
  `HowTo` (`templates/guides/_how_to_steps.html.twig`, 5 étapes) et `FAQPage`
  (`templates/guides/_faq.html.twig`) — les trois lus depuis les mêmes clés de traduction que le
  contenu visible, donc jamais désynchronisés. Pas d'`Article` (aurait nécessité une date de
  publication fictive).
- **Contenu appareil-spécifique** vérifié via recherche web contre les pages d'aide officielles
  Garmin Connect, Wahoo et OsmAnd avant rédaction (jamais de procédure inventée) — voir les
  sections « Getting the file onto your… » de chaque guide. Aucune affirmation de partenariat ou
  d'intégration officielle ; chaque page porte la même formule de non-affiliation
  (`guides.disclaimer`, réutilisée depuis `llms.txt`).
- **Tracking GTM** : 3 nouveaux évènements (`conversion_started`, `conversion_completed`,
  `gpx_downloaded` — absents du site avant cette phase) plus `landing_page` ajouté aux évènements
  déjà existants (`begin_checkout`, `purchase`) et à un nouveau `sign_up` (déclenché sur le flash
  d'inscription réussie). Attribution minimaliste : une seule clé `localStorage`
  (`assets/app/src/lib/attribution.ts`), jamais expirée, posée uniquement par les 3 pages du
  cluster — une visite de la page d'accueil ne l'écrase jamais. Aucune donnée d'itinéraire
  (URL Google Maps, origine/destination, coordonnées, GPX) n'est jamais envoyée, conformément à la
  politique déjà en place (voir `documentation/technique/google-tag-manager.md`).
- **Limite connue, documentée plutôt que masquée** : l'inscription via Google Sign-In ne passe pas
  par le flash utilisé pour déclencher `sign_up` côté inscription classique — ce chemin n'est donc
  pas encore suivi. À instrumenter si l'attribution Google Sign-In devient nécessaire.

## Opportunités futures — non implémentées

Les variantes suivantes, mentionnées dans le brief comme pistes possibles, ne sont **pas**
construites dans cette phase. Elles ne doivent être envisagées que si Google Search Console montre
une demande de recherche réelle pour ces intentions plus spécifiques — pas simplement parce
qu'elles figurent sur une liste de mots-clés :

- Google Maps → moto / vélo (variantes par mode de transport)
- Google Maps → iPhone / Android (variantes par plateforme)
- Google Maps → Garmin Zumo / Garmin Edge (variantes par modèle d'appareil)
- Google Maps → Wahoo ELEMNT (variante par modèle d'appareil)

Le principe reste : une intention de recherche = une page, décidée à partir de données réelles de
recherche, pas listée à l'avance.
