# Vision produit

## En une phrase

**Google Maps to GPX in one click — on the web or directly from Chrome.**

## Le problème

Les utilisateurs qui planifient un itinéraire dans Google Maps n'ont aucun moyen simple
d'exporter cet itinéraire vers leur GPS ou leur application de randonnée/cyclisme (Garmin,
Wahoo, OsmAnd, Locus Map, Strava...). SmartGPX résout ce problème précis, et propose en
complément une boîte à outils complète de conversion de formats GPS.

## Deux moteurs complémentaires

- **Moteur d'acquisition** : outils gratuits (visionneuse GPX, conversions de formats, GPX →
  Google Maps, simplification, fusion) + SEO. Ils amènent des utilisateurs vers SmartGPX.
- **Moteur de revenu** : conversion payante Google Maps → GPX (système de crédits) + extension
  Chrome, qui monétise ces utilisateurs.

Cette distinction structure explicitement l'architecture (voir
`documentation/technique/architecture.md`), les analytics et l'UX : **aucune friction payante
n'est ajoutée aux outils gratuits**, sauf changement délibéré du modèle économique.

## Différenciation

Contrairement aux références du marché, SmartGPX fournit une **extension Chrome de
production** : le flux le plus rapide de conversion se fait directement depuis Google Maps, en
un clic, sans repasser par le site web.

## Ce que SmartGPX n'est pas

- Pas un clone : aucun code, copie, design ou asset propriétaire d'un concurrent n'est réutilisé.
  Équivalence fonctionnelle uniquement — implémentation, UX et code entièrement propres à
  SmartGPX.
- Pas un produit qui invente des partenariats : le compatibilité avec Garmin/Wahoo/OsmAnd/Strava
  s'entend au sens « génère des fichiers dans un format que ces applications savent lire », pas
  au sens d'un partenariat API.

## Sources de vérité

Ce document résume la vision ; le détail fonctionnel est dans
`documentation/fonctionnel/fonctionnalites.md`, la tarification dans
`documentation/fonctionnel/pricing.md`, les parcours utilisateurs dans
`documentation/fonctionnel/parcours-utilisateur.md`.
