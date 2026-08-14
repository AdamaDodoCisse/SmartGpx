# SmartGPX

Google Maps to GPX in seconds — on the web or directly from Chrome. A commercial SaaS + GPS
route conversion toolbox, with a paid credit-based core converter (Google Maps → GPX) and a
suite of free, browser-side GPS format tools.

Read `documentation/fonctionnel/vision-produit.md` for the product vision and
`documentation/technique/architecture.md` for the architecture before doing significant
implementation work. Architectural decisions live in `documentation/decisions/ADR-*.md` — check
there before re-deciding something already settled.

## Stack

- Backend: PHP 8.4, Symfony 8.1, MySQL 8 (Doctrine ORM), Redis (cache + rate limiter).
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

## Local development

MySQL and Redis are assumed already installed and running locally (no Docker Compose — a
deliberate Phase 1 choice). Put real connection strings in `.env.local` (dev) and
`.env.test.local` (tests) — both gitignored.

```
symfony serve                    # or: php -S 127.0.0.1:8000 -t public
cd assets/app && npm run dev     # Vite dev server (HMR) — run alongside the PHP server
```

Database:

```
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:migrate --env=test
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
  Shared/          # genuinely cross-domain code only (e.g. TimestampableTrait)
  Controller/       # top-level pages with no dedicated domain yet (Home, Pricing, Legal)
  # Admin/ — Phase 8

assets/app/src/
  entries/         # Vite entry points (one per React island)
  components/       # shadcn/ui primitives + layout components + conversion/ (ConvertHero), extension/ (ExtensionConnect), tools/ (free GPS tools, Phase 5)
  gps/              # shared client-side conversion engine — gpx/kml/simplify/merge (Phase 5), kmz/tcx/fit/geojson (Phase 6) — all implemented

chrome-extension/    # separate npm project — Manifest V3 extension (Phase 3)
  src/popup/         # popup UI (React)
  src/background/    # service worker — the only code that reads the stored token
  src/lib/           # env, auth, api, mapsUrl, messages, i18n

templates/         # Twig — every public page
translations/       # Twig i18n catalogs (messages.{en,fr}.yaml)
migrations/         # Doctrine migrations
documentation/      # fonctionnel/ (product), technique/ (implementation), decisions/ (ADRs)
```

## Current architectural state (through Phase 6)

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
the stored token. See `documentation/technique/chrome-extension.md`. **Manual end-to-end
verification (real Chrome, real Google Maps route) is still pending** — see
`chrome-extension/RELEASE_CHECKLIST.md`.

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

Everything else (SEO content, admin) is out of scope until later phases — see the implementation
order in the product brief and the phase notes scattered through `documentation/fonctionnel/*.md`.
