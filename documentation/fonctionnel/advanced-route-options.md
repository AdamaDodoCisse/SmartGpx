# Options avancées de routage

**Statut : implémenté (Phase 10).** Étend le convertisseur principal (Google Maps → GPX, voir
`documentation/fonctionnel/fonctionnalites.md`). Détail technique complet dans
`documentation/technique/routing-options.md` et
[ADR-008](../decisions/ADR-008-routing-provider-capabilities.md).

## Principe : le parcours standard ne change pas

Coller un lien, cliquer "Convert to GPX" — exactement comme avant. Les réglages avancés vivent
derrière un panneau replié par défaut ("Advanced options"), jamais dans le chemin principal. Un
utilisateur qui ne l'ouvre jamais ne voit rien de nouveau.

## Ce que le panneau propose

- **Style d'itinéraire** : trois raccourcis (Fastest / Road trip / Motorcycle) qui pré-remplissent
  les réglages ci-dessous ; toute modification manuelle bascule automatiquement sur "Custom".
- **Mode de transport** : voiture, moto, vélo, marche (transports en commun conservé, déjà
  existant avant cette phase) — uniquement les modes que le fournisseur actif dit supporter.
- **Éviter** : autoroutes, péages, ferries. Des préférences, jamais des garanties — Google peut
  renvoyer un itinéraire qui les emprunte si aucune alternative raisonnable n'existe, et l'UI le
  dit explicitement (bulle d'aide sur chaque case).
- **Calcul de l'itinéraire** : standard, avec trafic actuel, ou le calcul le plus précis avec
  trafic — uniquement proposé pour les modes qui le supportent réellement (voiture, moto).
- **Étapes** : optimisation automatique de l'ordre des étapes par Google (affiche ensuite l'ordre
  d'origine et l'ordre optimisé, avant/après) ; chaque étape peut être marquée "Stop" (une
  halte réelle) ou "Via" (l'itinéraire doit y passer sans que ce soit un arrêt) et réordonnée
  manuellement (boutons haut/bas — voir la note sur le glisser-déposer plus bas).
- **Résultat** : détail de tracé standard ou haute qualité, affichage des itinéraires alternatifs,
  affichage d'un itinéraire économe en carburant.

Quand plusieurs itinéraires sont demandés (alternatives et/ou économe en carburant), un écran
« Choose your route » les présente avant l'export — durée, distance, ce qu'ils évitent, péage
estimé si disponible. Rien n'est facturé avant que l'utilisateur choisisse effectivement lequel
exporter.

## Ce qui a délibérément changé par rapport au brief initial

- **Glisser-déposer non implémenté** — le brief le mentionne comme optionnel ("éventuellement").
  Des boutons haut/bas donnent le même résultat (réordonner les étapes) sans dépendance
  supplémentaire et avec une meilleure accessibilité clavier.
- **Le coût en crédits ne change pas encore.** Chaque conversion, options avancées ou non, coûte
  toujours 1 crédit. Le brief demande explicitement une classification (STANDARD/ADVANCED,
  implémentée et visible dans la réponse) prévue pour un usage *ultérieur* par le système de
  crédits — pas pour cette phase.
- **Extension Chrome non concernée.** Elle continue d'envoyer une requête minimale
  (`{url, travelMode}`) et se comporte exactement comme avant ; le panneau d'options avancées et
  l'écran de choix d'itinéraire n'existent que sur le site web.

## Vérification effectuée

Conversion réelle Paris → Orléans → Lyon avec péages évités, contre l'API Google Routes en
production : itinéraire recalculé en conséquence, préférence appliquée affichée dans le résultat.
Flux complet "Choose your route" exercé avec alternatives et route économe en carburant réelles
(pas simulées) : sélection d'un itinéraire alternatif, export, GPX généré et téléchargé, exactement
1 crédit débité.
