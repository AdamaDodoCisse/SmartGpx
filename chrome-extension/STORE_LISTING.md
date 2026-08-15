# Chrome Web Store listing

Ready-to-paste content for every field of the Chrome Web Store Developer Dashboard, organized by
the tab it belongs in (**Store listing**, **Privacy practices**, **Distribution**). Not yet
submitted — see `RELEASE_CHECKLIST.md` for what's still blocking that (mainly: a real production
domain to replace the `PROD_API_ORIGIN`/privacy-policy-URL placeholders, and the developer account
itself).

## Store listing tab

### Name (45 characters max)

```
SmartGPX – Google Maps to GPX
```

29 characters. Matches `manifest.config.ts`'s `name` field exactly — the store listing name and
the installed extension's name should always be identical, so nothing to translate/adjust here.

### Summary (132 characters max — shown in search results, the single highest-leverage SEO field)

**English** (109 characters):
```
Convert any Google Maps route to a GPX file in one click: works with Garmin, Wahoo, OsmAnd, Strava, and more.
```

**French** (113 characters):
```
Convertissez un itinéraire Google Maps en fichier GPX en un clic : compatible Garmin, Wahoo, OsmAnd, Strava, etc.
```

Both name every device/app people actually search for ("garmin gpx export", "google maps to
wahoo", "osmand import route") rather than generic phrasing — Chrome Web Store's search ranks
heavily on exact-term matches in the summary and name, more than in the long description.

### Detailed description (16,000 characters max — supports plain-text line breaks, no markdown/HTML rendering)

**English:**
```
Turn any Google Maps route into a GPX file you can load onto a Garmin, Wahoo, OsmAnd, Locus Map,
Strava, or any GPS device or hiking/cycling app that reads GPX, directly from the Google Maps
tab you're already on.

HOW IT WORKS
1. Open a route on Google Maps (directions between two or more points).
2. Click the SmartGPX icon in your toolbar.
3. Click Export. The GPX file downloads immediately, ready to import.

No copying links, no pasting into a separate website, no redrawing the route by hand.

WORKS WITH YOUR GPS DEVICES AND APPS
GPX is the standard format read by Garmin Connect, Wahoo ELEMNT, OsmAnd, Locus Map, Strava,
Komoot, and most GPS devices and outdoor apps. Export once from Google Maps, import anywhere.

SIMPLE, TRANSPARENT PRICING
Your first conversion is free, no credit card required. After that, conversions use credits from
your SmartGPX account, purchased once as needed starting at $4.99. No subscription, and credits
never expire.

PRIVACY BY DESIGN
SmartGPX only reads the URL of the Google Maps tab you're actively viewing, and only at the
moment you click the toolbar icon. No background tracking, no browsing history access, no content
script silently watching your tabs, no data sold to third parties. Full permission-by-permission
disclosure is in our privacy policy.

MORE FREE TOOLS AT SMARTGPX.COM
Beyond Google Maps conversion, SmartGPX also offers a full suite of free, browser-only GPS format
tools: a GPX viewer, converters between GPX, KML, KMZ, TCX, FIT, and GeoJSON, plus route
simplification and merging. Everything runs entirely in your browser and stays free.

Questions or feedback? Visit smartgpx.com or reach us from the extension's popup.
```

**French:**
```
Transformez n'importe quel itinéraire Google Maps en fichier GPX utilisable sur un Garmin, un
Wahoo, OsmAnd, Locus Map, Strava, ou toute application ou appareil GPS qui lit le format GPX,
directement depuis l'onglet Google Maps que vous avez déjà ouvert.

COMMENT ÇA MARCHE
1. Ouvrez un itinéraire sur Google Maps (calcul d'itinéraire entre deux points ou plus).
2. Cliquez sur l'icône SmartGPX dans votre barre d'outils.
3. Cliquez sur Exporter. Le fichier GPX se télécharge immédiatement, prêt à être importé.

Pas de lien à copier, pas de site tiers où le coller, pas de tracé à redessiner à la main.

COMPATIBLE AVEC VOS APPAREILS ET APPLICATIONS GPS
Le GPX est le format standard lu par Garmin Connect, Wahoo ELEMNT, OsmAnd, Locus Map, Strava,
Komoot, et la plupart des appareils GPS et applications outdoor. Exportez une fois depuis Google
Maps, importez partout.

TARIFICATION SIMPLE ET TRANSPARENTE
Votre première conversion est gratuite, sans carte bancaire. Ensuite, les conversions utilisent
les crédits de votre compte SmartGPX, achetés une fois selon vos besoins à partir de 4,99 $.
Aucun abonnement, les crédits n'expirent jamais.

CONFIDENTIALITÉ PAR CONCEPTION
SmartGPX ne lit l'URL de l'onglet Google Maps que vous consultez activement qu'au moment où vous
cliquez sur l'icône de la barre d'outils. Aucun suivi en arrière-plan, aucun accès à votre
historique de navigation, aucun script de contenu qui observerait vos onglets en silence, aucune
donnée revendue à des tiers. Le détail complet, permission par permission, est disponible dans
notre politique de confidentialité.

PLUS D'OUTILS GRATUITS SUR SMARTGPX.COM
Au-delà de la conversion Google Maps, SmartGPX propose aussi une boîte à outils complète et
gratuite pour les formats GPS : visionneuse GPX, convertisseurs entre GPX, KML, KMZ, TCX, FIT et
GeoJSON, simplification et fusion de traces. Tout s'exécute entièrement dans votre navigateur et
reste gratuit.

Des questions ou des retours ? Rendez-vous sur smartgpx.com ou contactez-nous depuis le popup de
l'extension.
```

### Category

**Productivity** — the closest fit in Chrome Web Store's fixed taxonomy for "converts data for
use in another tool." Re-check the exact category list live in the dashboard before submitting;
taxonomy changes occasionally.

### Language(s)

English (primary) + French — add both as separate listing locales in the dashboard using the
paired copy above, rather than only submitting the English version. The site itself is fully
bilingual (see `documentation/fonctionnel/vision-produit.md`), so a French-only searcher on the
Store should find a French listing, not just an English one with a French-speaking audience
guessed at.

### Screenshots

**Done** — see the existing section below (unchanged from the previous draft).

### Promotional images (optional, but improves placement odds)

Not yet produced — genuinely optional (the listing is fully submittable without them), only
needed for certain search/category placements and any editorial featuring:
- **Small promo tile**: 440×280 PNG or JPEG.
- **Marquee**: 1400×560, only used if Google features the extension.

Ask if you'd like these generated the same way the icons were (SVG source, brand tokens, rendered
via a headless browser) — a quick follow-up, not blocking submission.

## Privacy practices tab

### Single purpose description

```
Exports the Google Maps route currently open in the active browser tab to a GPX file, using the
signed-in user's SmartGPX account credits. The extension has no other function.
```

### Permission justifications (paste one per field)

| Permission | Justification |
|---|---|
| `activeTab` | Used to read the URL of the Google Maps tab the user is currently viewing, but only at the moment they click the extension's toolbar icon — never continuously, and never for tabs the popup hasn't been opened on. This lets the extension detect a Google Maps route without requesting host permissions for google.com or injecting a content script. |
| `storage` | Stores the user's SmartGPX authorization token locally (`chrome.storage.local`) so they don't have to reconnect their account every time they open the popup. |
| `downloads` | Used exactly once per export, to save the generated `.gpx` file to the user's downloads folder. This is the only Manifest V3 mechanism available to a service worker for saving a file. |
| Host permission (SmartGPX API origin only) | Lets the background service worker call the SmartGPX API to convert the detected route and check the user's credit balance. Scoped to exactly the SmartGPX API domain — no other site is ever contacted. |
| Remote code | This extension does not execute remote code. All logic ships inside the packaged extension; the only network calls are `fetch()` requests to the SmartGPX API for data, never for code. |

Full detail (including what's deliberately *not* requested and why) is in `PRIVACY_DISCLOSURE.md`
— the source of truth this table is drawn from; keep both in sync if permissions ever change.

### Data usage disclosure (the checkbox grid)

- **Authentication information** — YES. The revocable SmartGPX account token
  (`chrome.storage.local`), used solely to authenticate API calls this extension makes on the
  user's behalf.
- Everything else Google's checklist asks about (personally identifiable information beyond the
  auth token, health info, financial/payment info, personal communications, location, web
  browsing history, user activity beyond the single active-tab read at click time, website
  content) — **NO** to all.
- Certifications (all three should be checked truthfully, as-is):
  - Does not sell or transfer user data to third parties outside approved use cases. ✅
  - Does not use or transfer user data for purposes unrelated to the item's single purpose. ✅
  - Does not use or transfer user data to determine creditworthiness or for lending purposes. ✅

### Privacy policy URL

```
https://smartgpx.com/privacy
```

Pending the real production domain (`PROD_API_ORIGIN` in `src/lib/env.ts` is still a placeholder —
see `RELEASE_CHECKLIST.md`). Once real, this must be a publicly reachable URL *before*
submission — Chrome Web Store validates it.

## Distribution tab

- **Visibility**: Public.
- **Pricing**: Free (the extension itself never charges anything directly — it spends credits
  already purchased on smartgpx.com; there is nothing for the Chrome Web Store's own payment
  system to handle).
- **Regions**: Worldwide, no reason to restrict.

## Support / contact fields

- **Website**: `https://smartgpx.com`
- **Support email**: the address `CONTACT_RECIPIENT_EMAIL` resolves to in production (placeholder
  `support@smartgpx.com` in `.env` — see `documentation/technique/deploiement.md`), or point
  users to `/contact` instead if a dedicated support inbox isn't ready yet.

## Screenshots

**Done.** Four real screenshots in `store-assets/`, composed onto a 1280×800 canvas framed like a
Chrome toolbar dropdown (a raw 320px-wide popup image alone reads too small/bare for a store
listing): `screenshot-1-popup-disconnected.png`, `screenshot-2-popup-route-detected.png`,
`screenshot-3-popup-export-success.png` (all captured against the real built extension loaded
unpacked, with `chrome.tabs.query`/`chrome.runtime.sendMessage` mocked so no live backend or
account was needed — see `documentation/technique/chrome-extension.md` for how), and
`screenshot-4-account-extensions.png` (`/account/extensions`, a real logged-in page, no mocking).

## Icons

**Done.** Real artwork now in `icons/` (16/32/48/128px) — a pine-green badge (`--primary`) with a
white open topographic contour ring and a trail-blaze orange (`--route`) waypoint dot, matching
the site's "topo trail" identity (see CLAUDE.md). Same mark used for the web favicon
(`public/favicon.svg`) and the popup/site/admin header marks — one design, one source of truth
(the inline SVG in `templates/_macros/logo.html.twig` and `chrome-extension/src/popup/components/icons.tsx`'s
`LogoMark`), so a future redesign only needs updating in those places plus re-rasterizing the PNGs.
All four icon sizes were regenerated with a proper alpha channel (previously exported without one,
leaving an opaque box behind the badge's rounded corners — fixed alongside the same bug in the web
favicon/apple-touch-icon).
