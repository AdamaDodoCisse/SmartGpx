# Options avancées de routage

**Statut : implémenté (Phase 10).** Étend `documentation/technique/google-maps-to-gpx.md` et
`documentation/technique/routing-provider.md`. Décision architecturale complète dans
[ADR-008](../decisions/ADR-008-routing-provider-capabilities.md).

## Ce que chaque option fait, et son mapping Google

Toutes les options vivent dans `App\Routing\ValueObject\RouteOptions` — jamais un nom spécifique
à Google (`avoidHighways`, pas `GoogleAvoidHighways`), voir ADR-008.

| Option SmartGPX | Champ Google Routes API | Contrainte |
| --- | --- | --- |
| `modifiers->avoidHighways/avoidTolls/avoidFerries` | `routeModifiers.avoidHighways/avoidTolls/avoidFerries` | DRIVE/TWO_WHEELER uniquement |
| `routingPreference` (`TRAFFIC_UNAWARE`\|`TRAFFIC_AWARE`\|`TRAFFIC_AWARE_OPTIMAL`) | `routingPreference` | DRIVE/TWO_WHEELER uniquement |
| `optimizeWaypointOrder` | `optimizeWaypointOrder` + lecture de `optimizedIntermediateWaypointIndex` en réponse | tous modes, uniquement si des étapes intermédiaires existent |
| `routeDetail` (`STANDARD`\|`HIGH_QUALITY`) | `polylineQuality` (`OVERVIEW`\|`HIGH_QUALITY`) | tous modes |
| `computeAlternativeRoutes` | `computeAlternativeRoutes` | tous modes |
| `showFuelEfficientRoute` | `requestedReferenceRoutes: ["FUEL_EFFICIENT"]` | DRIVE uniquement |
| `showTollEstimates` (+ `vehicleProfile` optionnel) | `extraComputations: ["TOLLS"]` (+ `routeModifiers.vehicleInfo.emissionType`) | DRIVE/TWO_WHEELER uniquement |
| type de waypoint `STOP`\|`VIA` | `intermediates[].via: true` pour VIA | — |

Ces contraintes sont appliquées à deux endroits, jamais laissées à la seule discipline de
l'appelant :

1. `GoogleRoutesProvider` n'inclut le champ dans la requête HTTP que si le mode de transport le
   supporte — la contrainte réelle de l'API Google, pas une supposition.
2. `App\Conversion\Service\GoogleMapsRouteOptionsMapper` filtre déjà en amont selon
   `RoutingProviderCapabilities` — une option demandée que le fournisseur actif ne supporte pas
   est silencieusement ignorée, jamais une erreur (le principe du brief produit : "ne jamais
   afficher/envoyer une option non supportée").

Formulations UI — jamais une garantie : "Prefer routes that don't use highways", jamais
"Guarantees no highways" ; un péage est toujours "Estimated tolls", jamais un prix garanti. Voir
`assets/app/src/i18n/locales/{en,fr}/common.json`, clés `convert.advanced.*`.

## STOP vs VIA, et optimisation de l'ordre des étapes

`GoogleMapsUrlParser` ne distingue jamais STOP de VIA — un lien Google Maps ne porte pas cette
information. Le type de chaque étape vient exclusivement de l'UI (`waypointTypes` dans la
requête, un `'STOP'|'VIA'` par étape dans l'ordre où `GoogleMapsUrlParser` les a extraites ;
toute position omise vaut `STOP`). `ConvertGoogleMapsToGpxAction::buildRouteWaypoints()` combine
les deux pour produire les `RouteWaypoint` envoyés au fournisseur.

Le réordonnancement manuel des étapes (haut/bas dans le panneau, pas de glisser-déposer — voir
plus bas) change l'ordre dans lequel elles sont envoyées ; `optimizeWaypointOrder` est un
réordonnancement *algorithmique* distinct effectué par Google, dont le résultat
(`optimizedIntermediateWaypointIndex`, une permutation des index d'origine) est renvoyé dans
`RouteResult::$optimizedWaypointOrder`, stocké sur `Conversion`, et affiché en
avant/après (`originalStopOrder`/`optimizedStopOrder` dans `ConversionJsonPresenter::toArray()`).

**Écart assumé par rapport au brief produit** : le brief mentionne le glisser-déposer comme
« éventuellement » (explicitement optionnel). Le panneau utilise des boutons haut/bas à la place
— même résultat fonctionnel (réordonner manuellement), sans dépendance de glisser-déposer
supplémentaire et avec une meilleure accessibilité clavier, cohérent avec la préférence du projet
pour les solutions natives du navigateur plutôt que des bibliothèques.

## Classification de coût (STANDARD / ADVANCED)

`App\Routing\Service\RouteOptionsCostClassifier` — une classification interne SmartGPX, pas un
tarif Google. ADVANCED dès qu'une option élargit la forme de la requête au-delà du calcul de
route de base (`computeAlternativeRoutes`, `showFuelEfficientRoute`, `showTollEstimates`) ; tout
le reste (évitements, préférence trafic, optimisation des étapes, détail de route) reste
STANDARD. Stockée sur `Conversion::$costTier` et renvoyée dans la réponse JSON, mais **ne
détermine aujourd'hui aucun coût réel en crédits** — le tarif reste fixe à 1 crédit quelle que
soit la classification, conformément au brief produit ("cette information doit pouvoir servir
*ultérieurement*"). Un changement de tarification devra élargir `ReserveCreditAction`/
`CreditAccountRepository`/`ConsumeReservedCreditAction`, qui sont câblées sur un montant fixe de 1
à trois endroits distincts (voir `documentation/technique/credit-system.md`) — non fait dans
cette phase, délibérément.

## Le flux en deux temps : alternatives et route économe en carburant

`computeAlternativeRoutes`/`showFuelEfficientRoute` peuvent renvoyer plusieurs itinéraires
candidats — l'utilisateur doit en choisir un avant que quoi que ce soit ne soit facturé. Un flux à
une seule étape ne convient pas ici (impossible de savoir combien de crédits réserver avant de
savoir combien de routes reviennent, et facturer sur un calcul non retenu par l'utilisateur serait
incorrect). D'où :

```
POST /api/conversions/google-maps/preview { url, ..., showAlternativeRoutes: true }
  → PreviewGoogleMapsRoutesAction : parse, calcule via computeRoutes(), AUCUNE réservation de crédit
  → RoutePreviewStore::store() : met en cache (Redis, pool cache.app, TTL 10 min) le calcul complet
    sous un previewId opaque, scellé à l'id de l'utilisateur
  → réponse : { previewId, candidates: [{ index, routeLabel, distanceMeters, durationSeconds, ... }] }

POST /api/conversions/google-maps/export { previewId, selectedIndex }
  → ExportPreviewedRouteAction : relit le cache (jamais un recalcul), vérifie le propriétaire et
    l'index, réserve puis consomme 1 crédit, persiste la Conversion pour l'itinéraire choisi
  → aperçu retiré du cache après un export réussi (usage unique) ; laissé jusqu'à expiration
    naturelle en cas d'échec (ex. crédits insuffisants), pour permettre une nouvelle tentative
    sans recalcul
```

**Seul ce chemin est concerné.** Quand ni l'un ni l'autre n'est demandé (le cas par défaut, et
celui de l'extension Chrome aujourd'hui), `ConvertGoogleMapsToGpxAction` garde son comportement
historique à une étape — un utilisateur qui n'ouvre jamais "Advanced options" ne déclenche jamais
le chemin preview/export. Volontairement limité au web : l'extension Chrome n'a pas d'UI de choix
d'itinéraire dans cette phase (hors périmètre), donc aucun contrôleur `/preview`/`/export`
équivalent n'existe côté `App\Extension\`.

## Analyse gratuite de l'URL (waypoints STOP/VIA avant conversion)

`POST /api/conversions/google-maps/parse` — accessible aux visiteurs anonymes (comme le reste du
formulaire principal, voir Phase 9), sans crédit, sans jamais appeler l'API de calcul d'itinéraire
payante (`GoogleMapsUrlParser::parse()` ne fait qu'une requête HTTP pour résoudre un lien court le
cas échéant). Utilisée par le panneau d'options avancées pour peupler la liste des étapes dès que
le panneau est ouvert avec une URL déjà saisie (debounce 500 ms côté client). Limitée par un
rate-limiter dédié (`limiter.route_parse`, 60/heure par IP — plus généreux que `limiter.conversion`
puisqu'aucun crédit n'est en jeu).

## Presets

`App\Routing\Enum\RoutePreset` (`FASTEST`, `ROAD_TRIP`, `MOTORCYCLE`) + résolution serveur
(`App\Routing\Service\RoutePresetOptionsResolver`, une table `match`, pas une succession de
`if`). Le backend est la source de vérité unique : la requête accepte un champ `preset` optionnel,
résolu en `RouteOptions` avant application des champs explicites de la même requête (qui
l'emportent champ par champ — permet au frontend d'envoyer "ROAD_TRIP mais j'ai changé
avoidTolls" sans dupliquer la table de résolution en TypeScript). Le frontend
(`assets/app/src/components/conversion/routing/types.ts`, `PRESET_OPTIONS`) ne garde qu'une copie
locale à but d'affichage immédiat (le panneau doit refléter le preset choisi sans attendre une
réponse serveur) — à garder synchronisée avec le resolver si l'un des deux change. `CUSTOM` n'est
jamais envoyé au backend : dès qu'un champ est modifié manuellement après un preset, le frontend
bascule son propre état visuel sur "Custom" et envoie les champs `RouteOptions` explicites au lieu
d'un nom de preset.
