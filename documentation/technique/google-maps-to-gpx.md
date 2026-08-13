# Google Maps → GPX

**Statut : implémenté (Phase 2), vérifié contre l'API Google Routes réelle.**

## Flux de bout en bout

```
POST /api/conversions/google-maps { url, travelMode? }
  → GoogleMapsUrlParser::parse()
      → résolution du lien court si nécessaire (maps.app.goo.gl, goo.gl)
      → format documenté (?api=1&origin=...) ou format "chemin" (best-effort)
      → chaque origine/étape/destination passe par RouteLocationParser
        (Address ou Coordinates — voir ADR-001)
  → ReserveCreditAction (échoue immédiatement si solde insuffisant, rien d'externe appelé)
  → RoutingProviderInterface::computeRoute() (GoogleRoutesProvider en prod, FakeRoutingProvider en test)
      → échec → ReleaseReservedCreditAction, 0 crédit débité
  → Conversion::fromRoute() (entité d'historique/affichage)
  → ConsumeReservedCreditAction (persiste la Conversion + ligne de ledger CONVERSION)
  → réponse JSON (origine, destination, étapes, distance, durée, mode, nombre de points, downloadUrl)

GET /api/conversions/{publicId}/gpx
  → régénère le GPX à la demande depuis les données stockées (GpxGenerator)
```

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

## Crédits

Voir `documentation/technique/credit-system.md` et
[ADR-002](../decisions/ADR-002-credit-ledger.md). En résumé : seule cette conversion consomme un
crédit, débité uniquement en cas de succès.

## Vérification effectuée

Itinéraire réel Cergy → Paris via l'API Google Routes en production : 35,6 km, ~61 minutes,
300 points de trace, GPX de 15 Ko généré et téléchargé avec succès ; solde décrémenté
correctement ; seconde tentative à solde nul rejetée en HTTP 402.
