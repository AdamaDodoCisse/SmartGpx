# Google Maps → GPX

**Statut : implémenté (Phase 2), vérifié contre l'API Google Routes réelle. Options avancées
étendues Phase 10 — voir `documentation/technique/routing-options.md`.**

## Flux de bout en bout — cas standard (comportement inchangé depuis la Phase 2)

```
POST /api/conversions/google-maps { url, travelMode?, ...options avancées optionnelles }
  → GoogleMapsUrlParser::parse()
      → résolution du lien court si nécessaire (maps.app.goo.gl, goo.gl)
      → format documenté (?api=1&origin=...) ou format "chemin" (best-effort)
      → chaque origine/étape/destination passe par RouteLocationParser
        (Address ou Coordinates — voir ADR-001)
  → GoogleMapsRouteOptionsMapper (requête → RouteOptions, filtré par RoutingProviderCapabilities)
  → ReserveCreditAction (échoue immédiatement si solde insuffisant, rien d'externe appelé)
  → RoutingProviderInterface::computeRoutes() (GoogleRoutesProvider en prod, FakeRoutingProvider en test)
      → échec → ReleaseReservedCreditAction, 0 crédit débité
  → Conversion::fromRoute() (entité d'historique/affichage, options appliquées incluses)
  → ConsumeReservedCreditAction (persiste la Conversion + ligne de ledger CONVERSION)
  → réponse JSON (origine, destination, étapes, distance, durée, mode, options appliquées,
    coût, péage estimé, nombre de points, downloadUrl)

GET /api/conversions/{publicId}/gpx
  → régénère le GPX à la demande depuis les données stockées (GpxGenerator)
```

Ce flux à une étape reste utilisé pour tout ce qui ne produit qu'un seul itinéraire (évitements,
trafic, optimisation des étapes, détail de route, péages) — un client qui n'envoie que
`{url, travelMode}` (l'extension Chrome) obtient exactement le comportement d'avant les options
avancées, byte pour byte. Voir `documentation/technique/routing-options.md` pour le flux en deux
temps (`/preview` puis `/export`) utilisé uniquement quand des itinéraires alternatifs ou une
route économe en carburant sont demandés.

## Formats d'URL supportés

Aucune spécification officielle de Google ne couvre ces formats — voir
`App\Conversion\Parser\GoogleMapsUrlParser`. Trois niveaux de support assumés :

1. **Fiable** : le format documenté `google.com/maps/dir/?api=1&origin=...&destination=...&waypoints=A|B&travelmode=driving`
   — origine, destination, étapes et mode de transport tous fiables.
2. **Supporté avec réserve affichée** : le format « chemin » que la plupart des utilisateurs
   collent réellement (copié depuis la barre d'adresse en consultant un itinéraire), ex.
   `/maps/dir/Cergy,+France/Paris,+France/@48.9,2.2,10z/data=!3e0`. Les emplacements sont
   fiables ; le mode de transport encodé dans le paramètre `data=` n'est **pas** décodé
   (non documenté, risque de rupture silencieuse) — le mode est marqué `travelModeInferred: true`
   et l'UI propose un sélecteur de mode pré-rempli mais modifiable avant envoi.
3. **Explicitement non supporté**, erreur claire : liens de consultation seule
   (`/maps/place/...`), recherche, lien à un seul point, hôte non-Google.

Les liens courts (`maps.app.goo.gl/...`, `goo.gl/maps/...`) sont résolus via une requête HTTP
(suivi de redirection) avant toute analyse — voir `GoogleMapsShortLinkResolver`.

## GPX généré

GPX 1.1 valide via `DOMDocument` (voir `App\Conversion\Gpx\GpxGenerator`) : un `<wpt>` par
origine/étape/destination avec des coordonnées toujours résolues par le fournisseur de routing
(jamais re-géocodées), un `<trk>/<trkseg>` avec la géométrie complète de l'itinéraire. Pas
d'élément `<ele>` : l'API Google Routes v2 ne renvoie pas l'altitude dans la forme de réponse
utilisée ici — omission valide au regard du schéma GPX 1.1 (élément optionnel), pas un défaut.
Depuis la Phase 10, un bloc `<extensions>` optionnel sous `<metadata>` porte les options de
routage appliquées (`<smartgpx:routeOptions travelMode="..." avoidHighways="..." .../>`) — un
élément schema-légal en GPX 1.1, absent si aucune `Conversion` n'a encore été générée avec ces
métadonnées.

## Crédits

Voir `documentation/technique/credit-system.md` et
[ADR-002](../decisions/ADR-002-credit-ledger.md). En résumé : seule cette conversion consomme un
crédit, débité uniquement en cas de succès.

## Vérification effectuée

Itinéraire réel Cergy → Paris via l'API Google Routes en production : 35,6 km, ~61 minutes,
300 points de trace, GPX de 15 Ko généré et téléchargé avec succès ; solde décrémenté
correctement ; seconde tentative à solde nul rejetée en HTTP 402.
