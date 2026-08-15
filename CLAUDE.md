# SmartGPX

Google Maps to GPX in seconds — on the web or directly from Chrome. A commercial SaaS + GPS
route conversion toolbox, with a paid credit-based core converter (Google Maps → GPX) and a
suite of free, browser-side GPS format tools.

Read `documentation/fonctionnel/vision-produit.md` for the product vision and
`documentation/technique/architecture.md` for the architecture before doing significant
implementation work. Architectural decisions live in `documentation/decisions/ADR-*.md` — check
there before re-deciding something already settled.

## Stack

- Backend: PHP 8.4, Symfony 8.1, MySQL 8 (Doctrine ORM, dev/prod) / SQLite (tests), Redis (cache
  + rate limiter).
- Frontend: React + TypeScript + Vite + Tailwind CSS v4 + shadcn/ui + Lucide React, under
  `assets/app/`.
- Rendering: Symfony/Twig server-renders all public pages; React is used only as "islands" for
  interactive widgets, mounted into specific DOM regions — see
  `documentation/decisions/ADR-004-seo-rendering.md`. Never build a page as a client-only SPA
  shell.

## Hard rules

- **One class = one use case.** Business logic lives in `Action` classes
  (`execute(...): Result`), never in controllers. Controllers are a thin HTTP bridge: Request →
  DTO → Validation → Action → Response. No `XxxManager` god classes.
- **Interfaces only at true external boundaries** (a real third-party API, a payment provider) —
  not speculatively. `RoutingProviderInterface` (implemented, Phase 2 — see
  `documentation/decisions/ADR-001-routing-provider.md`) and `BillingProviderInterface`
  (implemented, Phase 4 — see `documentation/decisions/ADR-006-billing-provider.md`) are the
  only ones planned/existing.
- **The Chrome extension is a separate npm project** (`chrome-extension/`), not part of
  `assets/app/` — see `documentation/decisions/ADR-005-extension-authentication.md`. It
  authenticates via a revocable opaque token (`ExtensionAuthorization`), never a session cookie.
- **Free format-conversion tools run entirely client-side**, never touching the Symfony backend
  — see `documentation/decisions/ADR-003-browser-conversions.md`. Only Google Maps → GPX talks
  to the backend, because only it needs a routing-provider secret.
- **Credits**: only a successful Google Maps → GPX conversion consumes a credit. Every other
  tool is and stays free unless the business model is deliberately changed.
- **Only a verified account may complete a Google Maps → GPX conversion** — enforced once, in
  `ConvertGoogleMapsToGpxAction::execute()` (Phase 9), so both the web controller and the Chrome
  extension controller inherit it automatically. Don't re-check `isVerified()` per-controller;
  add any future gate to the shared action instead.
- **Secrets** (routing API keys, Stripe keys, mailer credentials, DB password) never live in
  `.env`/`.env.dev` (placeholders only, committed) — only in gitignored `.env.local` /
  `.env.test.local`. Never expose them in React, the Chrome extension, error messages, or logs.
- **Language conventions**: code identifiers in English; code comments in French; all
  `documentation/` content and ADRs in French; application UI copy in English (default) and
  French.
- **PHPStan level 8** and `php-cs-fixer` must stay clean — run `composer qa` before considering
  any backend change done.
- **No new Doctrine migrations** (as of Phase 8) — sync schema via `doctrine:schema:update
  --force` instead; see "Local development" below. `ROLE_ADMIN` is granted only via
  `bin/console app:user:promote-admin <email>`, never from the admin UI itself — see
  [ADR-007](documentation/decisions/ADR-007-admin-access-control.md).
- **Avoid generic "AI-generated" visual design**, on this project and by default in general: warm
  cream (`#F4F1EA`-ish) + serif + terracotta; near-black + a single neon/acid accent; centered
  hero with a big stat + gradient blob; Inter/Space Grotesk as the safe default; emoji as section
  markers; `rounded-lg` and rounded accent bars on everything. SmartGPX's own identity (Phase 9,
  see `assets/app/src/entries/app.css`) is a "topo trail" palette instead — a pine-green
  `--primary`, a cool stone/sage `--background` neutral (not warm cream), and a trail-blaze
  orange `--route` accent reserved for the homepage's signature route-line visual and directly
  related elements, never used as a general button/link color. Typography pairs `--font-display`
  (Fjalla One, condensed — headings, the wordmark, short labels) with the system sans for body
  text and `--font-mono` (IBM Plex Mono) for coordinate-like/data accents (eyebrow tags, step
  counters). Extend this token system for new pages rather than reaching for a different palette
  or typeface per page. The logo mark (pine-green badge, white contour ring, orange waypoint dot —
  Phase 9) is defined once per stack, `templates/_macros/logo.html.twig` (Twig) and `LogoMark` in
  `chrome-extension/src/popup/components/icons.tsx` (React) — reuse these rather than re-drawing
  it or reaching for a generic icon.
- **Tailwind v4 `@theme` gotcha**: `--color-background: var(--background)` (and the other
  `--color-*` tokens `@theme` generates `bg-background`/`text-muted-foreground`/etc. from) does
  **not** dynamically re-resolve `var(--background)` per descendant element — it inherits
  whatever value was already resolved at `:root`. To scope a light-themed card inside a
  dark-themed section (or vice versa) regardless of the page's own light/dark mode, override the
  `--color-*` variables directly on that element, never the intermediate semantic tokens
  (`--background`, `--foreground`, …) — overriding only the latter has no effect on the generated
  utility classes. See `.hero-card-scope` in `assets/app/src/entries/app.css` (Phase 9) for the
  working pattern, applied to both the hero's decorative panel and the homepage pricing cards.
  Also in `app.css`: the hero-specific tokens (`--hero-bg-start`, `--hero-fg`, …) are deliberately
  defined once in `:root` only, *not* duplicated in the dark-mode override blocks used elsewhere
  — the hero and the dark pricing section always render the same rich dark look regardless of the
  site's own theme, a deliberate exception to the app's usual three-state
  light/dark/system theming, not an oversight. **`.hero-card-scope` itself has two different
  legitimate usages that must not be conflated** (found the hard way in Phase 10): a real light
  floating card (`HeroCard.tsx`, the decorative hero panel — both also set `bg-background`
  explicitly) versus a transparent, outline-only card sitting directly on `.dark-section` (the
  homepage pricing cards — no `bg-*` class at all, by design, so their plain text is meant to
  inherit the section's own light `color`). Adding `color: var(--color-foreground)` to the shared
  `.hero-card-scope` rule to fix illegible plain text in `ConvertResult` broke the pricing cards
  the opposite way (dark text on a transparent-over-dark card). Fixed per-usage instead —
  `text-foreground` added directly on `HeroCard`'s own element — rather than changing the shared
  class again.

## Local development

MySQL and Redis are assumed already installed and running locally (no Docker Compose — a
deliberate Phase 1 choice) for the **dev** environment. Put real connection strings in
`.env.local` (dev) — gitignored.

```
symfony serve                    # or: php -S 127.0.0.1:8000 -t public
cd assets/app && npm run dev     # Vite dev server (HMR) — run alongside the PHP server
```

Dev database schema (no migration files — see below):

```
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php bin/console app:credit-pack:seed-launch-grid   # only needed once, on a fresh database
```

**Tests run against SQLite, not MySQL** (`.env.test`'s `DATABASE_URL`, a file under `var/`) — no
server to start, and `config/packages/doctrine.yaml`'s `when@test` block overrides the driver
accordingly. **New schema changes are never migrations** (as of Phase 8) — sync straight from
entity mappings with `doctrine:schema:update --force`, dev and test alike; the handful of
`migrations/*.php` files already in the repo are Phase 1–7 history, already applied, left as-is
— don't add new ones. `tests/bootstrap.php` seeds the `credit_pack` launch grid once per test run
(invoking `app:credit-pack:seed-launch-grid` in-process) since `schema:update` only syncs DDL,
never data. Recreate the test database after any entity change:

```
rm -f var/test.db
php bin/console doctrine:schema:update --force --env=test
```

## Commands

```
composer qa          # cs-check + phpstan + phpunit — the "is this done" gate
composer test         # phpunit only
composer stan          # phpstan only
composer cs-fix         # php-cs-fixer, auto-fixing

cd assets/app
npm run dev            # Vite dev server
npm run build           # production build → public/build/
npm run typecheck        # tsc --noEmit
npm run test             # vitest unit tests (gps/ engine modules)

cd chrome-extension
npm run dev            # Vite dev server (extension HMR)
npm run build           # production build → dist/ (load unpacked from here)
npm run typecheck        # tsc --noEmit
npm run test             # vitest unit tests
```

## Where things live

```
src/
  Identity/       # auth (Phase 1) — reference domain shape for what follows
  Routing/         # RoutingProviderInterface + GoogleRoutesProvider/FakeRoutingProvider (Phase 2); RouteOptions/capabilities/presets (Phase 10)
  Conversion/       # Google Maps URL parsing, GPX generation, Conversion entity/API (Phase 2); preview/export flow, free URL parsing (Phase 10)
  Usage/            # credit ledger (CreditAccount/CreditTransaction), reserve/consume/release (Phase 2)
  Extension/        # ExtensionAuthorization, token authenticator, /api/extension/* (Phase 3)
  Billing/          # CreditPack/CreditPurchase, BillingProviderInterface + StripeBillingProvider (Phase 4)
  Shared/          # genuinely cross-domain code only (e.g. TimestampableTrait, Pagination/)
  Controller/       # top-level pages with no dedicated domain yet (Home, Pricing, Legal, Guides, Sitemap, Robots)
  Admin/            # admin back-office: Controller/ + Metrics/ + ComputeAdminMetricsAction (Phase 8) — mutations live in the domain they mutate, not here
  Contact/          # /contact — Request/Form/Mailer/Action, rate-limited like registration (Phase 9)

assets/app/src/
  entries/         # Vite entry points (one per React island)
  components/       # shadcn/ui primitives + layout components + conversion/ (ConvertHero), extension/ (ExtensionConnect), tools/ (free GPS tools, Phase 5)
  gps/              # shared client-side conversion engine — gpx/kml/simplify/merge (Phase 5), kmz/tcx/fit/geojson (Phase 6) — all implemented

chrome-extension/    # separate npm project — Manifest V3 extension (Phase 3)
  src/popup/         # popup UI (React)
  src/background/    # service worker — the only code that reads the stored token
  src/lib/           # env, auth, api, mapsUrl, messages, i18n

templates/         # Twig — every public page (guides/ — 8 SEO content pages + index, Phase 7)
translations/       # Twig i18n catalogs (messages.{en,fr}.yaml)
migrations/         # Doctrine migrations
documentation/      # fonctionnel/ (product), technique/ (implementation), decisions/ (ADRs)
```

## Current architectural state (through Phase 10)

**Phase 1 — Foundation**: Symfony backend skeleton, MySQL/Doctrine, full email+password auth
(registration, email verification, login with throttling, forgot/reset password),
React/Vite/Tailwind/shadcn frontend scaffold, homepage/pricing/legal page shells, `/fr` locale
routing, CI.

**Phase 2 — Google Maps → GPX**: the revenue engine, implemented and verified against the real
Google Routes API. `RoutingProviderInterface`/`GoogleRoutesProvider`/`FakeRoutingProvider`
(`src/Routing/`), Google Maps URL parsing + GPX 1.1 generation + the `Conversion` history entity
and JSON API (`src/Conversion/`), and a concurrency-safe credit ledger with welcome credit
(`src/Usage/` — see `documentation/decisions/ADR-002-credit-ledger.md`). The homepage hero is now
a live `ConvertHero` island. See `documentation/decisions/ADR-001-routing-provider.md` and
`documentation/technique/google-maps-to-gpx.md`.

**Phase 3 — Chrome extension**: a dedicated, stateless `api_extension` firewall
authenticates the extension via a revocable opaque token (`ExtensionAuthorization`,
`src/Extension/`), never a session cookie — see
`documentation/decisions/ADR-005-extension-authentication.md`. `/account/extensions` lets a user
connect a browser (handoff via `externally_connectable` + `chrome.runtime.sendMessage`, no
copy-paste) and revoke it at any time. The extension itself (`chrome-extension/`, a separate npm
project, Manifest V3) reuses `ConvertGoogleMapsToGpxAction` unchanged via
`ExtensionConversionController`; its background service worker is the only code that ever reads
the stored token. See `documentation/technique/chrome-extension.md`. **Manually verified
end-to-end** against a real Chrome browser and a real Google Maps route — see
`chrome-extension/RELEASE_CHECKLIST.md`. One real bug found and fixed in the process: the popup's
"connect your account" button opened the POST-only handoff-completion route directly instead of
the account page carrying the real button.

**Phase 4 — Stripe billing**: credit-pack purchases via hosted Stripe Checkout.
`BillingProviderInterface`/`StripeBillingProvider`/`FakeBillingProvider` (`src/Billing/`) mirror
the Phase 2 routing-provider pattern exactly. `CreditPack` (DB-backed pricing catalog, seeded by
migration) replaces the old hardcoded `/pricing` grid; `CreditPurchase` tracks a Checkout Session
through to confirmation, keyed by a unique Stripe session id, which is what makes
`GrantPurchasedCreditsAction` idempotent against Stripe's at-least-once webhook delivery — proven
by a functional test that posts the same webhook event twice and asserts credits land only once.
A dedicated `api_billing_webhook` firewall (`security: false`, no `User` to authenticate) hands
all trust to signature verification inside the controller. See
`documentation/decisions/ADR-006-billing-provider.md` and `documentation/technique/stripe.md`.
Manually verified end-to-end against a real Stripe test-mode account (`stripe listen`, test
card) — real webhook signature, real crediting, idempotence confirmed against a real
`stripe events resend` redelivery.

**Phase 5 — Free client-side GPS tools**: GPX Viewer, GPX → Google Maps, GPX Simplify, GPX
Merge, KML → GPX, GPX → KML — six pages, zero backend calls (`src/Controller/ToolsController.php`
only renders Twig shells that mount React islands; all parsing/generation runs in the browser via
`assets/app/src/gps/{gpx,kml,simplify,merge}/`, native `DOMParser`/`XMLSerializer`, no XML
library). A generic `SingleFileConverterTool` component (upload → parse → generate → download)
powers the two KML/GPX converters and is designed for the four Phase 6 format converters to reuse
without modification. GPX Viewer uses Leaflet + OpenStreetMap tiles — no API key, consistent with
these tools staying genuinely free to run. See `documentation/technique/gpx.md`,
`documentation/technique/kml-kmz.md` (KML half), and
`documentation/decisions/ADR-003-browser-conversions.md`.

**Phase 6 — Remaining free client-side GPS tools**: the last seven format converters — KMZ →
GPX, TCX ↔ GPX, FIT ↔ GPX, GeoJSON ↔ GPX — completing the free-tools set (13 pages total). Two
new dependencies, both justified at true external-format boundaries: `fflate` (`gps/kmz/`, ZIP
extraction with a pre-inflate size guard against zip bombs plus a path-traversal guard, no
`generateKmz` — one-directional only) and `@garmin/fitsdk` (`gps/fit/`, the official Garmin FIT
SDK; `generateFit` targets a minimal running-activity profile only — see
`documentation/technique/fit.md` for the documented limitation). `gps/tcx/` and `gps/geojson/`
stay native (`DOMParser`/`JSON`), matching the Phase 5 precedent of preferring browser built-ins
over libraries wherever the format allows it. `SingleFileConverterTool` and `downloadFile` were
widened (a `readAs: 'text' | 'arrayBuffer'` prop, `string | ArrayBuffer` content) to support
binary formats without changing Phase 5's existing text-based tools. See
`documentation/technique/{kml-kmz,tcx,fit,geojson}.md`.

**Phase 7 — SEO content + sitemap infrastructure**: the "+ SEO" half of the acquisition engine
(`vision-produit.md`). Eight static, server-rendered guide pages (format comparisons —
GPX vs KML/TCX/FIT/GeoJSON — plus how-tos for Google Maps → GPX, KMZ, simplifying, and merging)
under `GuidesController` (`src/Controller/`), each ending in an internal link to the free tool or
paid converter it's about; EN/FR body prose lives inline in each Twig template, branched on
`app.request.locale`, rather than in the translation catalogs (unsuited to multi-paragraph
content). Also added, since none of it existed before: a `SitemapController` (`/sitemap.xml`,
hand-maintained public-route list, same pattern as the homepage tool map),
a `RobotsController` (`/robots.txt` — a controller, not a static file, so the sitemap URL it
points at is always correct for the current host), and canonical/hreflang `<link>` tags in
`base.html.twig`. The header nav (desktop and mobile) gained "Tools"/"Guides" links, since there
was previously no way to reach either from the site chrome. See `documentation/fonctionnel/guides.md`
and `documentation/technique/seo.md` — the latter documents a real regression (Phase 9) in this
exact canonical/hreflang mechanism, worth reading before touching it again.

**Phase 8 — Admin interface**: the last phase named in the product brief. A simple back-office
(`src/Admin/`) — dashboard metrics, a paginated user list with a per-user credit ledger and manual
credit-grant form, a purchase list, full `CreditPack` CRUD (create/edit/deactivate), and a newly
built failed-conversion log. `ROLE_ADMIN` is granted only via
`bin/console app:user:promote-admin <email>`, never from the UI itself; every admin route carries
`#[IsGranted('ROLE_ADMIN')]`, the same per-route pattern used everywhere else in the app. Failed
conversions weren't tracked at all before this phase — `ConversionFailure` (`src/Conversion/`) is
a new, always-fully-populated entity, wired into both `ConvertGoogleMapsController` and
`ExtensionConversionController`'s catch blocks via `LogConversionFailureAction`, so a failure
triggered from the web app or the Chrome extension is counted either way. Every admin mutation
lives in the domain of the entity it mutates (`GrantAdminCreditAdjustmentAction` in `Usage/`,
`CreateCreditPackAction`/`UpdateCreditPackAction` in `Billing/`) — `src/Admin/` itself holds only
controllers, templates, and the one cross-domain read (`ComputeAdminMetricsAction`). Pagination
(`src/Shared/Pagination/`) wraps Doctrine ORM's own paginator, no new dependency. Also: schema
management switched from Doctrine migrations to `doctrine:schema:update --force` (see "Local
development" above), and the test suite now runs against SQLite instead of MySQL. See
`documentation/fonctionnel/admin.md`, `documentation/technique/admin.md`, and
[ADR-007](documentation/decisions/ADR-007-admin-access-control.md).

**Phase 9 — Visual identity, revenue-path hardening, and a full QA pass**: not named in the
original product brief (Phases 1–8 were) — scoped and built directly from a systematic QA sweep
plus explicit follow-up requests, rather than a pre-written spec. Several distinct pieces:

- **Real visual identity, replacing every placeholder**: no logo asset existed anywhere before
  this phase — no web favicon at all, a text-only header wordmark, and the Chrome extension's
  `icons/icon{16,32,48,128}.png` were literal placeholder solid-color PNGs. One SVG mark (a
  pine-green badge, white open topographic contour ring, trail-blaze-orange waypoint dot — see
  the "topo trail" token system below) defined once per stack (`templates/_macros/logo.html.twig`
  for Twig, `LogoMark` in `chrome-extension/src/popup/components/icons.tsx` for React) and reused
  everywhere: the web favicon, the site header, the admin header, the extension popup, and the
  extension's own icon files. The `chrome-extension/STORE_LISTING.md` and `RELEASE_CHECKLIST.md`
  drafts (pre-existing, never completed) now have their real icons and screenshots — what's left
  before an actual Chrome Web Store submission (`PROD_API_ORIGIN`, a real developer account) is
  explicitly out of reach of this repo, documented as such in `RELEASE_CHECKLIST.md`.
- **Only verified accounts may complete a Google Maps → GPX conversion** — enforced in
  `ConvertGoogleMapsToGpxAction::execute()` (a new `EmailNotVerifiedException`, checked first,
  before any credit reservation), so both the web controller and the Chrome extension controller
  are covered by the one check. Previously nothing enforced this at all, even though an unverified
  user could already log in.
- **The homepage conversion form is visible to anonymous visitors** — since Google Maps → GPX is
  the paid, revenue-driving feature, hiding it behind a sign-in wall worked against the business
  goal. The real form now renders for everyone; only submitting it (not landing on the page) tells
  an anonymous visitor to sign up, or a signed-in-but-unverified one to verify their email first.
  All widget states (idle, loading, success, no-credit, sign-in-required, email-not-verified)
  share one `HeroCard` component so state changes read as one object updating, not different UI
  blocks appearing.
- **All 13 free tool pages** gained real explanatory content (previously bare dropzones), and the
  **GPX Viewer had a real, fully-reproducible bug** (only part of the map rendered until an
  interaction shifted what was visible) — root cause: `leaflet.css` was never linked in the
  production build (`vite_entry_script_tags` alone doesn't emit an entry's CSS, only
  `vite_entry_link_tags` does), invisible under `vite dev` since that injects CSS live with no
  `<link>` needed.
- **Header and footer redesign**, plus two real bugs found and fixed along the way: the header
  always showed a static "Login" link regardless of auth state (now a real Account dropdown with
  Credits/Extensions/Log out), and the mobile menu only listed 6 of the 15 real nav destinations
  with hardcoded English-only paths (a French visitor tapping a translated label landed on the
  English route) — now sourced from Twig's `path()` instead of being duplicated in TypeScript.
- **CI was silently broken**: PHPStan needs `var/cache/dev`'s compiled container, which no step
  warmed, and the test job still provisioned MySQL + ran Doctrine migrations, a Phase-8-stale setup
  (tests have used SQLite + `schema:update` since Phase 8 — see "Local development" above) that
  left CI's schema silently missing every entity change since. Fixed and verified locally end to
  end under conditions matching CI exactly (no live database, freshly warmed dev cache only).
- **Terms of Service and Privacy Policy got real content** (previously one-sentence placeholders),
  and `/contact` (a new `src/Contact/` domain — request/form/mailer/action, rate-limited like
  registration) plus `/account/credits` (a credit-ledger page, `src/Usage/Controller/`) were built
  where no such page existed before.
- **Homepage redesign, taking structural and visual-identity inspiration from a competitor**
  (gpx2maps.com) while deliberately keeping SmartGPX's own "topo trail" palette rather than
  copying theirs (violet + photography) — a bolder *treatment* in SmartGPX's own colors, not a
  reskin. The hero got a deep pine-green gradient background (`.hero-gradient`) with a large
  abstract terrain-silhouette SVG standing in for a photo (`.hero-terrain`, zero licensing risk,
  infinitely scalable, consistent with the existing `.contour-bg` motif) and a stat strip (13
  free tools, 2 languages, 1 free conversion — real numbers, not padded to match a competitor's).
  The small pricing-preview card was replaced with a real, dark-toned pricing showcase
  (`.dark-section`) built from live `CreditPack` data; the pack-card markup itself was extracted
  into `templates/pricing/_pack_grid.html.twig`, `{% include %}`d from both `/pricing` and the
  homepage, so there's one source of truth for a pricing card. A condensed guide section
  (`home.guide.*`) was added between pricing and the FAQ — real prose explaining *why* the
  conversion needs a paid routing call, distinct from the FAQ's one-liners, ending in a link to
  the full `/guides/google-maps-to-gpx` guide. Deliberately **not** built: a second "features
  grid" duplicating differentiators (compatibility, privacy, no-subscription) that already have
  their own full sections further up the same page — judged as page bloat rather than a genuine
  gap, since gpx2maps needed that grid for content this page already had spread across dedicated
  sections.
- **Redesign of the 13 free tool pages**: previously an identical, minimal skeleton (flat icon
  badge, `<h1>`, one explainer paragraph) with **zero CTA or cross-linking between the 13 tools**
  and no link to `/pricing` anywhere. Extended the homepage's visual language down to these pages
  via three shared partials in `templates/tools/` — `_header.html.twig` (route-accent icon badge,
  category eyebrow, bigger title, on a `.contour-bg` band — not the homepage's full
  `.hero-gradient`, deliberately, to avoid repeating that signature treatment 13 times),
  `_related.html.twig` (2–3 curated cross-links per page, sized to the item count — a `grid-cols-3`
  with only 2 items left a visible empty cell, caught and fixed during this work), and
  `_cta.html.twig` (one soft, informational card per page — 12 pages link to the paid converter,
  `gpx_to_google_maps` itself links to the Chrome extension instead). The CTA copy stays
  non-gating by design: `documentation/fonctionnel/vision-produit.md` states plainly that no
  payment friction gets added to the free tools, so these are plain cross-links, never a modal or
  blocker. Pure Twig/translations/CSS — no backend or React changes.

Also worth knowing: `documentation/technique/seo.md` documents a real regression this phase found
and fixed — canonical/hreflang tags were silently missing site-wide since Phase 8, because the
request attribute the code read (`_canonical_route`) is never actually populated by Symfony at
runtime, only `_route` and `_locale` are; no test caught it because none existed for this behavior
(one does now).

**Phase 10 — Advanced Route Options**: a full 30-section brief extending the Google Maps → GPX
converter with route-planning options, built in full (the user explicitly opted out of a reduced
scope). See `documentation/fonctionnel/advanced-route-options.md`,
`documentation/technique/routing-options.md`, and
[ADR-008](documentation/decisions/ADR-008-routing-provider-capabilities.md) for complete detail;
summary:

- **`RoutingProviderInterface` gained `capabilities(): RoutingProviderCapabilities` and a
  `RouteOptions`-aware `computeRoutes()`** (replacing `computeRoute()` — see ADR-001's Phase 10
  update). Real Google Routes API fields now wired: `routeModifiers` (avoid highways/tolls/
  ferries), `routingPreference` (traffic-aware routing), `optimizeWaypointOrder`,
  `computeAlternativeRoutes`, `requestedReferenceRoutes` (fuel-efficient route),
  `extraComputations` (tolls), `polylineQuality` — each sent only when the travel mode actually
  supports it (DRIVE/TWO_WHEELER for most; DRIVE only for fuel-efficient). Never a hardcoded
  `25`-waypoint limit either — `capabilities()->maxIntermediateWaypoints` is the one source of
  truth. `new RouteOptions()` (the parameter default) reproduces the exact pre-Phase-10 behavior,
  so every existing caller — including the Chrome extension, unmodified — keeps working
  byte-for-byte.
- **STOP vs VIA waypoints, and a two-phase preview/export flow for alternatives.** Comparing
  alternative or fuel-efficient routes can't be one atomic step like the standard flow — Google
  returns multiple candidates, and a credit must only be spent on the one actually chosen.
  `PreviewGoogleMapsRoutesAction` computes and caches candidates (Redis, `cache.app`, 10-minute
  TTL, sealed to the user id) with **zero credit reservation**; `ExportPreviewedRouteAction`
  re-reads the cache (never recomputes) and only then reserves/consumes. Triggered only when the
  frontend requests alternatives/fuel-efficient — the default single-shot
  `ConvertGoogleMapsToGpxAction` flow is structurally untouched. Deliberately web-only: the
  Chrome extension has no route-choice UI in this phase, so no `/preview`/`/export` equivalent
  exists under `src/Extension/`.
- **`RoutingFeatureCostTier` (STANDARD/ADVANCED) is classified and exposed, but doesn't change
  credit cost yet** — the brief itself frames this as for *later* use by the credit system, not
  this phase. Every conversion still costs a flat 1 credit.
- **A free `POST /api/conversions/google-maps/parse`** (no credit, no Google Routes call —
  `GoogleMapsUrlParser` doesn't need one) lets the advanced panel populate the STOP/VIA waypoint
  list before any conversion; accessible to anonymous visitors like the rest of the main form,
  rate-limited separately (`limiter.route_parse`, IP-keyed, more generous than `limiter.conversion`
  since nothing is billable).
- **A server-side preset system** (`RoutePreset` enum + `RoutePresetOptionsResolver`, a `match`
  table) — the backend is the single source of truth for what a preset resolves to; the frontend
  only needs preset names/labels for the buttons, never duplicates the resolution table.
- **Frontend**: a new `AdvancedRouteOptions.tsx` panel (closed by default — the standard
  paste-URL → Convert workflow is unchanged for anyone who doesn't open it), gated section by
  section on the live `RoutingProviderCapabilities` fetched server-side and embedded in the
  homepage HTML (avoids an extra network round trip). New Radix primitives added from scratch —
  checkbox, radio-group, toggle-group, tooltip, collapsible — since only Button/Input/Sheet
  existed before. Waypoint reordering uses up/down buttons, not drag-and-drop: the brief marks
  drag-and-drop optional, and buttons are more accessible with no new dependency.
- **Two real bugs found while verifying against the live Google Routes API** (not simulated):
  the dev database schema hadn't been synced for the new `Conversion` columns (only the test DB
  had — `doctrine:schema:update --force` needs running for **both** after an entity change, not
  just `--env=test`), and the `.hero-card-scope` dual-usage bug described above.

This completes every phase named in the original product brief, plus two follow-up passes built
from explicit requests rather than the pre-written spec (Phase 9's QA/hardening sweep, Phase 10's
Advanced Route Options brief). Further work needs a new phase scoped explicitly first — see
`documentation/fonctionnel/vision-produit.md` and the ADRs before assuming a direction that hasn't
been decided yet.
