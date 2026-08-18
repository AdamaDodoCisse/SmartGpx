# Obtenir les premiers clients payants de SmartGPX — plan Google Ads

**Statut : plan rédigé, rien encore créé côté Google Ads/GTM.** Fondé sur l'état réel du pricing,
des pages de guides et du tracking GTM du projet (voir `documentation/fonctionnel/pricing.md`,
`documentation/seo/google-maps-device-cluster.md` et
`documentation/technique/google-tag-manager.md`) plutôt que sur des hypothèses génériques.

## Sommaire

1. [Objectif & réalité économique](#1-objectif--réalité-économique)
2. [Corriger le tracking avant de dépenser](#2-corriger-le-tracking-avant-de-dépenser)
3. [Créer le compte Google Ads, pas à pas](#3-créer-le-compte-google-ads-pas-à-pas)
4. [Structure des campagnes](#4-structure-des-campagnes)
5. [Mots-clés](#5-mots-clés)
6. [Annonces & extensions](#6-annonces--extensions)
7. [Pages de destination](#7-pages-de-destination)
8. [Budget & enchères](#8-budget--enchères--par-phases)
9. [Ciblage](#9-ciblage)
10. [Mots-clés négatifs](#10-mots-clés-négatifs-liste-partagée-tout-le-compte)
11. [Marques & conformité](#11-marques--conformité--contrainte-réelle-pas-de-la-paperasse)
12. [Rituel de suivi hebdomadaire](#12-rituel-de-suivi-hebdomadaire)
13. [Qui fait quoi](#13-qui-fait-quoi)

**⚠️ Ne pas dépenser avant l'étape 2.** Le conteneur GTM du site ne pousse aujourd'hui que des
événements GA4 — aucune action de conversion Google Ads n'existe. Dépenser du budget avant de
corriger ça revient à piloter sans compteur : aucune optimisation possible, aucune donnée
exploitable.

| | |
|---|---|
| Conversions payantes à ce jour | 0 |
| Objectif produit | Vente de crédits GPX |
| Pack cible réaliste | Popular · 9,99 $ |
| Marchés phase 1 | US · CA · UK · AU · IE |

---

## 1. Objectif & réalité économique

Le but de cette première phase n'est pas la rentabilité immédiate — c'est de générer assez de
vraies conversions pour que les enchères automatiques de Google aient quelque chose à apprendre,
tout en gardant un coût d'acquisition défendable.

**Le calcul qui compte** : Starter coûte 4,99 $ et la première conversion est déjà gratuite — un
clic payant qui n'achète qu'un Starter et ne revient jamais est à peine rentable, quel que soit le
CPA. Le plan cible donc **Popular (9,99 $)** comme achat réaliste, et compte sur le réachat
(crédits jamais expirés, aucun abonnement) pour la vraie rentabilité — pas sur le premier clic.

**Cible CPA — phase 1** : **6 à 8 $** de coût par acquisition pour les 4 à 6 premières semaines —
proche du seuil de rentabilité sur un seul achat Popular, assumé comme coût d'acquisition
volontaire, pas encore comme campagne rentable clic par clic.

## 2. Corriger le tracking avant de dépenser

Configuration exacte, champ par champ — le layer de données (`purchase`, `sign_up`) existe déjà
côté site et transporte déjà les bons champs (vérifié directement dans
`assets/app/src/billing/checkoutSuccessPolling.ts` et `templates/base.html.twig`) : rien à
changer côté code, seulement à câbler côté GTM/Ads.

> **⚠️ Ordre obligatoire** : les actions de conversion Google Ads (étape 1) doivent exister avant
> de créer les tags GTM (étape 3) — GTM a besoin de l'ID et de l'étiquette de conversion que
> Google Ads génère à la création. Faire les étapes dans cet ordre, pas en parallèle.

### Étape 1 — Google Ads : créer les deux actions de conversion

1. **Outils et paramètres → Conversions → « + Nouvelle action de conversion » → Site web.**
   URL du site : `smartgpx.com`.
2. **Action « Achat »** — Catégorie : *Achat*. Valeur : *« Utiliser une valeur différente pour
   chaque conversion »* (le champ `value` du dataLayer alimente déjà ça, pas de valeur fixe à
   saisir). Comptage : *Une par clic*. Fenêtre de conversion : 30 jours par défaut, suffisant tant
   qu'aucune donnée réelle ne dit le contraire. Une fois créée, Google Ads affiche un **ID de
   conversion** (format `AW-XXXXXXXXX`) et une **étiquette de conversion** — les noter, nécessaires
   à l'étape 3.
3. **Action « Inscription »** — même écran, nouvelle action. Catégorie : *Inscription*. Valeur :
   *Ne pas utiliser de valeur* (ou valeur fixe à 0 — un sign-up n'a pas de montant réel). Dans
   *Paramètres de la colonne* : cocher **« Ne pas inclure dans Conversions »** — c'est ce qui en
   fait un signal secondaire, sans polluer l'optimisation principale sur les achats. Noter
   l'ID/étiquette générés, distincts de ceux de l'action « Achat ».

### Étape 2 — GTM : variables de couche de données

Variables → Nouveau → *Variable de couche de données*, une par ligne ci-dessous. Si le tag GA4
`purchase` existe déjà dans le conteneur, ces variables existent probablement déjà sous un autre
nom — les réutiliser plutôt que d'en créer des doublons.

| Nom de la variable GTM | Nom de variable (couche de données) |
|---|---|
| `DLV - purchase value` | `value` |
| `DLV - purchase currency` | `currency` |
| `DLV - purchase transaction_id` | `transaction_id` |

### Étape 3 — GTM : déclencheurs

| Nom du déclencheur | Type | Condition |
|---|---|---|
| `CE - purchase` | Événement personnalisé | Nom de l'événement = `purchase` (déjà utilisé par le tag GA4 existant — le réutiliser, ne pas le dupliquer) |
| `CE - sign_up` | Événement personnalisé | Nom de l'événement = `sign_up` |

### Étape 4 — GTM : les 3 tags

1. **Conversion Linker** — Tags → Nouveau → type *Conversion Linker*. Déclencheur :
   *Initialization - All Pages* (pas « All Pages » — GTM recommande le déclencheur
   d'initialisation pour que ce tag se charge le plus tôt possible).
2. **Google Ads Conversion Tracking — Achat** — Type *Google Ads Conversion Tracking*. ID de
   conversion / Étiquette : ceux notés à l'étape 1 pour « Achat ». Valeur de conversion :
   `{{DLV - purchase value}}`. Code de devise : `{{DLV - purchase currency}}`. ID de transaction :
   `{{DLV - purchase transaction_id}}` — important ici spécifiquement : `ConfirmAnalyticsTrackingAction`
   garantit déjà côté backend qu'un même achat ne pousse l'événement `purchase` qu'une seule fois
   (verrou de ligne), mais l'ID de transaction donne à Google Ads une seconde barrière de
   déduplication, indépendante du site. Déclencheur : `CE - purchase`.
3. **Google Ads Conversion Tracking — Inscription** — même type de tag. ID/Étiquette : ceux notés
   pour « Inscription ». Pas de valeur/devise à mapper. Déclencheur : `CE - sign_up`.

> **⚠️ Ne pas créer de 4ᵉ tag.** `conversion_started`, `gpx_downloaded`, `pack_selected` restent
> en GA4 uniquement — aucune action de conversion Ads dessus. Trop en amont dans l'entonnoir, les
> transformer en conversions Ads dilue ce que « Maximiser les conversions » cherche à optimiser
> plus tard.

### Étape 5 — Tester avant de publier

GTM → Aperçu (mode preview), déclencher un achat de test (Stripe en mode test). Confirmer dans le
panneau Aperçu que `CE - purchase` se déclenche bien et que le tag Google Ads reçoit les 3 valeurs
mappées. Publier le conteneur seulement après ce contrôle. Revenir ensuite dans Google Ads →
Conversions : l'action « Achat » doit passer au statut *« Reçu récemment »* sous 24h — si elle
reste à *« Aucune conversion récente »* après un achat de test, le déclencheur ou le mapping de
variable est probablement en cause, pas Google Ads lui-même.

## 3. Créer le compte Google Ads, pas à pas

Si le compte n'existe pas encore : passer par le mode expert dès le départ, pas par le flux
« Smart » — ce dernier force une campagne générée automatiquement, mal ciblée pour un produit
aussi spécifique.

1. **ads.google.com → Créer un compte.** Sur l'écran d'accueil, cliquer « Passer en mode expert »
   (lien discret en bas du premier écran) avant de continuer — sinon Google Ads propose uniquement
   le flux Smart simplifié.
2. **Choisir l'objectif « Ventes » puis « Site web ».** Renseigner smartgpx.com. Ne pas laisser
   Google Ads « scanner automatiquement » et générer des mots-clés/annonces à ta place à cette
   étape — les ignorer, la structure de la section 4 remplace cette suggestion.
3. **Facturation.** Paramètres → Facturation. Mode de paiement + adresse de facturation
   (nécessaire avant toute diffusion, même en petit budget).
4. **Lier Google Analytics (GA4) au compte Ads.** Outils → Comptes liés → Google Analytics (GA4)
   → Lier. Permet d'importer les audiences GA4 plus tard, en plus des conversions déjà couvertes
   par la section 2.
5. **Créer une liste de mots-clés négatifs partagée.** Bibliothèque partagée → Listes de
   mots-clés à exclure → Nouvelle liste, nommée « SmartGPX — Exclusions compte ». Coller la liste
   de la section 10. L'appliquer à chaque campagne créée ensuite.

## 4. Structure des campagnes

Quatre campagnes Search, jamais une seule campagne fourre-tout — l'intention (et donc la bonne
page de destination) diffère nettement entre chacune.

| Campagne | Intention | Page de destination |
|---|---|---|
| Générique | « J'ai un itinéraire, il me faut un GPX » | `/` |
| Appareil — Garmin | « Je veux cet itinéraire sur mon Garmin » | `/guides/google-maps-to-garmin` |
| Appareil — Wahoo | Idem, Wahoo | `/guides/google-maps-to-wahoo` |
| Appareil — OsmAnd | Idem, OsmAnd | `/guides/google-maps-to-osmand` |

Ce n'est pas une nouvelle construction : ces 3 pages de guides embarquent déjà le vrai
convertisseur, un H1 déjà aligné sur cette intention précise, et l'attribution GTM `landing_page`
(`guide_google_maps_garmin`, etc.) qui remonte déjà jusqu'à l'événement `purchase`. Le SEO et le
payant peuvent donc partager le même modèle d'attribution dès le premier jour.

## 5. Mots-clés

Reprend les mots-clés déjà validés pour le cluster SEO
(`documentation/seo/google-maps-device-cluster.md`) plutôt que d'en réinventer — payant et SEO
ciblent la même intention commerciale déjà vérifiée. Exact et expression uniquement pour
commencer : le large risque de ramener du trafic hors-sujet (trackers GPS, applis fitness) qui ne
paiera jamais.

| Campagne | Mots-clés (exact / expression) |
|---|---|
| Générique | `[google maps to gpx]` · `"convert google maps to gpx"` · `"google maps gpx file"` · `"google maps route to gps"` |
| Garmin | `[google maps to garmin]` · `"google maps route to garmin"` · `"google maps to garmin connect"` · `"send google maps route to garmin"` |
| Wahoo | `[google maps to wahoo]` · `"google maps route to wahoo"` · `"gpx wahoo"` |
| OsmAnd | `[google maps to osmand]` · `"import google maps route into osmand"` |

> **À ne pas enchérir** : `strava`, `komoot` et les autres applications de planification
> d'itinéraires concurrentes — SmartGPX n'est pas un concurrent de ces applis, c'est un pont de
> format. Enchérir dessus achète des clics sans adéquation produit réelle.

## 6. Annonces & extensions

Format Search actuel de Google Ads : annonces responsives (RSA), jusqu'à 15 titres (30 caractères)
et 4 descriptions (90 caractères), assemblés automatiquement par Google. Copy directement puisée
dans le ton déjà établi du site (jamais « débloquez la puissance de… ») — vérifier les longueurs
exactes dans l'éditeur Google Ads, qui valide en direct.

### Campagne générique

```
smartgpx.com
Google Maps Route to GPX | Convert in Seconds | 1 Free Conversion
Paste your Google Maps route, get a real GPX file. Works with Garmin, Wahoo,
OsmAnd. No subscription — credits never expire.
```

**Titres** : « Google Maps Route to GPX » · « Convert to GPX in Seconds » · « 1 Free Conversion »
· « No Subscription Ever » · « Credits Never Expire » · « Works With Garmin, Wahoo+ » · « Paste
Link, Get GPX File » · « From $4.99, No Subscription »

**Descriptions** : « Paste your Google Maps route, get a real GPX file. Works with Garmin, Wahoo,
OsmAnd and more. » · « No subscription. Buy credits once, use anytime. Your first conversion is
free. » · « Real turn-by-turn routing, not a straight line. Multiple stops supported. »

### Campagnes appareil (exemple Garmin — dupliquer pour Wahoo/OsmAnd)

```
smartgpx.com › guides › garmin
Get Your Route on Garmin | Google Maps to Garmin GPX
Convert Google Maps to GPX for Garmin Connect. Not affiliated with Garmin.
First conversion free.
```

**Titres** : « Get Your Route on Garmin » · « Google Maps to Garmin GPX » · « Import as a Garmin
Course » · « Not Just a Straight Line » · « Works With Garmin Connect »

**Description obligatoire sur toutes les annonces appareil** : mention « Not affiliated with
Garmin/Wahoo/OsmAnd » — voir section 11, ce n'est pas optionnel.

### Extensions (assets) — à configurer une fois, au niveau du compte

- **Extensions de liens annexes** : Tarifs → `/pricing` · Comment ça marche →
  `/guides/google-maps-to-gpx` · Outils gratuits → `/guides` · Extension Chrome →
  `/#chrome-extension`
- **Extraits de prix** : Starter 4,99 $ · Popular 9,99 $ · Power 29,99 $ — les 3 vrais packs,
  directement depuis `/pricing`.
- **Extensions d'accroche** : « No Subscription » · « Credits Never Expire » · « 1 Free
  Conversion » · « Works With Garmin, Wahoo, OsmAnd »
- **Extrait structuré** : type « Appareils compatibles » : Garmin, Wahoo, OsmAnd, Locus Map,
  Strava — la même liste déjà affichée sur la page d'accueil.

## 7. Pages de destination

Chaque campagne pointe vers la page la plus proche de son intention exacte, pas vers la page
d'accueil par défaut — un meilleur Quality Score vient de cette cohérence
mot-clé/annonce/page, pas d'un budget plus élevé.

| Campagne | URL | Pourquoi elle convertit |
|---|---|---|
| Générique | `smartgpx.com/` | Le convertisseur est le premier élément visible, sans scroll. |
| Garmin | `/guides/google-maps-to-garmin` | H1, tutoriel et convertisseur déjà écrits pour cette recherche exacte. |
| Wahoo | `/guides/google-maps-to-wahoo` | Idem. |
| OsmAnd | `/guides/google-maps-to-osmand` | Idem. |

## 8. Budget & enchères — par phases

Aucune donnée de conversion n'existe encore : les enchères automatiques (Maximiser les
conversions/CPA cible) n'ont rien sur quoi apprendre au lancement — les activer à froid revient à
laisser Google deviner, avec ton budget.

1. **Phase 1 — Semaines 1-2 : CPC manuel ou « Maximiser les clics » plafonné.** ≈ 15-20 $/jour sur
   les 4 campagnes cumulées. Objectif unique : vérifier que le tag de conversion se déclenche
   correctement et obtenir les toutes premières conversions réelles.
2. **Phase 2 — Semaines 3-6, dès ~15-30 conversions cumulées.** Basculer en « Maximiser la valeur
   de conversion » sur les campagnes ayant atteint ce volume — probablement Générique et Garmin en
   premier, vu la taille de base installée.
3. **Phase 3 — Mois 2 et après.** Réallouer le budget vers la campagne au meilleur CPA/ROAS réel —
   exactement la décision que l'attribution `landing_page` a été construite pour éclairer, une
   lecture de donnée plutôt qu'une supposition.

**Répartition de départ suggérée** (à ajuster dès les premières données) : Générique 40 % ·
Garmin 35 % · Wahoo 15 % · OsmAnd 10 % — pondérée sur une estimation grossière de la base
installée par appareil.

> **Pas de Performance Max au lancement.** PMax s'appuie entièrement sur les enchères automatiques
> sur tout l'inventaire Google (Search, Display, YouTube, Discover, Gmail) — encore moins de
> données pour apprendre qu'une campagne Search seule. À réserver pour une phase 3+, une fois un
> volume réel de conversions Search établi.

## 9. Ciblage

Site en anglais par défaut avec une locale `/fr/`. Recommandation : campagnes en anglais ciblant
**US, Canada, Royaume-Uni, Australie, Irlande** en premier (plus grande base Garmin/Wahoo
anglophone, prix déjà natifs en USD). Garder le marché francophone pour une phase 2, une fois
l'entonnoir anglophone validé — lancer les deux en même temps dilue un budget déjà limité avant
qu'aucune campagne n'atteigne un volume de données exploitable.

Appareils : pas d'exclusion nécessaire (le convertisseur fonctionne sur mobile comme desktop) —
surveiller le taux de conversion par appareil après 2 semaines et ajuster les enchères par
appareil seulement à ce moment-là, jamais en devinant à l'avance.

## 10. Mots-clés négatifs (liste partagée, tout le compte)

`free` · `crack` · `download` · `jobs` · `careers` · `stock` · `review` · `api` · `sdk` ·
`strava` · `komoot`

« free »/« crack »/« download » seuls attirent des recherches d'outils gratuits ou piratés — un
modèle « 1 conversion gratuite » n'est pas ce que ces recherches attendent. « api »/« sdk »
signale une intention développeur, pas utilisateur final.

## 11. Marques & conformité — contrainte réelle, pas de la paperasse

> **⚠️ Politique de marques Google Ads.** Enchérir sur `garmin`/`wahoo`/`osmand` comme mots-clés
> est généralement autorisé, mais utiliser ces noms dans le texte de l'annonce d'une façon qui
> suggère une affiliation ou un partenariat ne l'est pas. Chaque annonce des campagnes appareil
> doit porter la même formule de non-affiliation déjà utilisée sur les pages de guides du site
> (« Not affiliated with Garmin/Wahoo/OsmAnd ») — l'omettre expose à une plainte pour marque qui
> peut faire retirer l'annonce en pleine campagne.

## 12. Rituel de suivi hebdomadaire

- [ ] Vérifier que l'action de conversion « Achat » a bien reçu des événements cette semaine (pas
      de silence suspect).
- [ ] Comparer le CPA par campagne à la cible de la section 1 — couper ou réduire ce qui dépasse
      largement sans amélioration après 1 semaine complète de données.
- [ ] Relire les termes de recherche réels (Rapports → Termes de recherche) — ajouter les
      hors-sujet à la liste négative partagée plutôt que de les laisser drainer le budget.
- [ ] Comparer les 4 campagnes entre elles via `landing_page` dans GA4 — quelle niche convertit
      réellement, pas seulement quelle niche clique.
- [ ] Dès ~15-30 conversions cumulées sur une campagne : basculer cette campagne en enchères
      automatiques (section 8, phase 2).

## 13. Qui fait quoi

**Ce que je peux faire** : préparer la configuration exacte du tag GTM de conversion (section 2),
écrire/ajuster les textes d'annonces et pages de destination si besoin, lire les rapports une fois
exportés pour aider à décider des ajustements. Pas de connexion API vers Google Ads ou Google Tag
Manager dans cet environnement — impossible de créer un compte, une campagne, ou une action de
conversion à distance.

**Ce qu'il reste à faire côté compte** : créer/posséder le compte Google Ads, la facturation,
les actions de conversion (section 2), et saisir les campagnes dans l'interface (sections 3-6) —
guidage possible écran par écran en direct, mais la main reste sur le compte du début à la fin.