# Chrome Web Store listing (draft)

Draft copy for the eventual store submission. Not yet submitted — see `RELEASE_CHECKLIST.md` for
what's still missing before that's possible.

## Name

SmartGPX – Google Maps to GPX

## Short description (≤132 characters)

Export your Google Maps route to GPX in one click, without leaving Google Maps.

## Detailed description

SmartGPX turns any Google Maps route into a GPX file you can load into your GPS device, hiking
app, or bike computer — directly from the Google Maps tab you're already on.

**How it works**

1. Open a route on Google Maps.
2. Click the SmartGPX icon.
3. Click Export — the GPX file downloads immediately.

**Pricing**

Your first conversion is free. After that, conversions use credits from your SmartGPX account
(purchased separately at smartgpx.com/pricing) — never a subscription, credits never expire.

**Privacy**

SmartGPX only reads the URL of the Google Maps tab you're actively viewing when you click the
extension icon — no browsing history, no background tracking, no data sold to third parties.
Full disclosure: see `PRIVACY_DISCLOSURE.md`.

## Category

Productivity

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
