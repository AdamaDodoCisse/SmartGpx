# FIT

**Statut : implémenté (Phase 6), vérifié par tests unitaires.**

`assets/app/src/gps/fit/index.ts`, [`@garmin/fitsdk`](https://www.npmjs.com/package/@garmin/fitsdk)
— le SDK FIT officiel publié par Garmin (zéro dépendance transitive), retenu plutôt qu'une
réimplémentation maison du format binaire FIT (protocole propriétaire Garmin, encodage
message-based avec CRC — pas un format texte comme GPX/KML/TCX/GeoJSON).

## Lecture (`parseFit`)

`Stream.fromByteArray` + `Decoder.isFIT`/`read()` extraient les `recordMesgs` (un message `RECORD`
par point échantillonné). Seuls les points portant `positionLat`/`positionLong` numériques sont
conservés — un fichier FIT peut légitimement n'avoir aucune position GPS (capteur home-trainer,
ceinture cardio seule) : dans ce cas `parseFit` lève une erreur explicite plutôt que de renvoyer
une trace vide silencieuse.

**Conversion semicercles → degrés** : FIT encode les coordonnées en *semicircles* (entier 32
bits signé, résolution `360° / 2^32`), pas en degrés décimaux :

```
degrés = semicircles × (180 / 2^31)
```

## Écriture (`generateFit`) — profil course à pied uniquement

`generateFit` cible un **profil minimal "activité course à pied"** : `FILE_ID` + un `RECORD` par
point + un `LAP` + une `SESSION` + une `ACTIVITY`, `sport: running` (constante FIT `1`),
`type: manual` (constante FIT `0`). Aucun agrégat calculé (distance, calories, allure moyenne…)
n'est fabriqué — ces champs sont absents plutôt qu'estimés arbitrairement.

**Constantes numériques du protocole FIT** : le `.d.ts` du SDK type `type`/`sport`/`manufacturer`
en `number` brut (pas en union de chaînes, malgré les exemples du README) — les valeurs réelles
ont été retrouvées en grepant le runtime du SDK (`@garmin/fitsdk`'s `profile.js`) : type de
fichier `4` = activity, sport `1` = running, type d'activité `0` = manual, fabricant `255` =
development (identifiant réservé aux logiciels tiers, pas un vrai fabricant de matériel).

**Limite assumée** : ce jeu de messages minimal n'a pas de source faisant autorité confirmant
qu'il s'agit du strict minimum accepté par tout logiciel/service tiers réel — seule
`Decoder.checkIntegrity()`/`read().errors` sur les propres fichiers générés par SmartGPX a été
vérifiée (voir tests). Un échec d'import signalé sur un service réel serait le signal pour
enrichir ce jeu de messages ; c'est un changement localisé à `generateFit`, pas une refonte.

**Asymétrie assumée** : `parseFit` accepte n'importe quel fichier FIT réel (issu d'une montre,
d'un capteur…), potentiellement bien plus riche que ce que `generateFit` sait produire. Aucune
boucle générer → parser → régénérer n'est donc testée à l'identique — seule la fidélité des
positions/altitudes/horodatages est vérifiée après un aller-retour.

Testé (`fit/index.test.ts`) : aller-retour position/altitude/temps via l'`Encoder` du SDK
lui-même (aucun fichier binaire `.fit` commité), tolérance `toBeCloseTo(…, 6)` liée à la
quantification semicircle ; `checkIntegrity()`/`errors` propres sur la sortie générée ; erreur
si aucun point ; repli sur `routes[0]` en l'absence de `tracks`; rejet d'un flux non-FIT; erreur
sur un fichier FIT valide mais sans aucune position GPS.
