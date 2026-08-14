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
  or typeface per page.

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
  Routing/         # RoutingProviderInterface + GoogleRoutesProvider/FakeRoutingProvider (Phase 2)
  Conversion/       # Google Maps URL parsing, GPX generation, Conversion entity/API (Phase 2)
  Usage/            # credit ledger (CreditAccount/CreditTransaction), reserve/consume/release (Phase 2)
  Extension/        # ExtensionAuthorization, token authenticator, /api/extension/* (Phase 3)
  Billing/          # CreditPack/CreditPurchase, BillingProviderInterface + StripeBillingProvider (Phase 4)
  Shared/          # genuinely cross-domain code only (e.g. TimestampableTrait, Pagination/)
  Controller/       # top-level pages with no dedicated domain yet (Home, Pricing, Legal, Guides, Sitemap, Robots)
  Admin/            # admin back-office: Controller/ + Metrics/ + ComputeAdminMetricsAction (Phase 8) — mutations live in the domain they mutate, not here

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

## Current architectural state (through Phase 8)

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
`base.html.twig` using Symfony's `_canonical_route` request attribute. The header nav (desktop and
mobile) gained "Tools"/"Guides" links, since there was previously no way to reach either from the
site chrome. See `documentation/fonctionnel/guides.md` and `documentation/technique/seo.md`.

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

This completes every phase named in the original product brief. Further work needs a new phase
scoped explicitly first — see `documentation/fonctionnel/vision-produit.md` and the ADRs before
assuming a direction that hasn't been decided yet.
