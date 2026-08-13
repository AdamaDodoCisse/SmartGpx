# API

**Statut : deux endpoints implémentés (Phase 2).** JSON plutôt qu'un formulaire Twig, pour
rester réutilisable par l'extension Chrome (Phase 3) sans modifier la couche Action — seule
l'authentification changera alors (jeton au lieu du cookie de session).

## `POST /api/conversions/google-maps`

Authentification : session (cookie), `#[IsGranted('ROLE_USER')]`. CSRF : en-tête
`X-CSRF-Token`, valeur lue depuis `data-csrf-token` sur `#convert-hero-root`
(`templates/home/index.html.twig`), jeton `convert_google_maps`. Limite de débit : 20/heure par
utilisateur (`config/packages/rate_limiter.yaml`, clé `conversion`).

Requête :
```json
{ "url": "https://www.google.com/maps/dir/...", "travelMode": "DRIVE" }
```
`travelMode` est optionnel (`DRIVE|WALK|BICYCLE|TRANSIT`) et prime toujours sur un mode déduit de
l'URL.

Réponse `200` :
```json
{
  "publicId": "019ffce9-666f-7b2d-bd85-b919833c00b6",
  "origin": "Cergy, France",
  "destination": "Paris, France",
  "stops": [],
  "distanceMeters": 35630,
  "durationSeconds": 3654,
  "travelMode": "DRIVE",
  "travelModeInferred": false,
  "trackPointCount": 300,
  "downloadUrl": "/api/conversions/019ffce9-666f-7b2d-bd85-b919833c00b6/gpx"
}
```

Erreurs :

| Statut | Cas |
|---|---|
| 402 | crédits insuffisants |
| 422 | URL invalide, lien Google Maps non supporté, ou aucun itinéraire trouvé |
| 429 | limite de débit atteinte |
| 503 | fournisseur de routing indisponible (détail journalisé côté serveur uniquement) |

Chaque erreur renvoie `{ "error": "<message traduit>" }`, traduit selon `User::getLocale()`
(catalogue `conversion.error.*` dans `translations/messages.{en,fr}.yaml`) — jamais de détail
d'implémentation ni de fragment de clé API dans le message.

## `GET /api/conversions/{publicId}/gpx`

Authentification identique. Régénère le GPX à la demande depuis les données de la conversion
(voir `documentation/technique/google-maps-to-gpx.md`) et le retourne en
`Content-Type: application/gpx+xml`. `publicId` est un UUID, jamais l'identifiant auto-incrémenté
interne (même logique anti-énumération que `User::publicId`).
