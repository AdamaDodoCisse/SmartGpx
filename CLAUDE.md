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
  (Phase 4) are the only ones planned/existing.
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
```

## Where things live

```
src/
  Identity/       # auth (Phase 1) — reference domain shape for what follows
  Routing/         # RoutingProviderInterface + GoogleRoutesProvider/FakeRoutingProvider (Phase 2)
  Conversion/       # Google Maps URL parsing, GPX generation, Conversion entity/API (Phase 2)
  Usage/            # credit ledger (CreditAccount/CreditTransaction), reserve/consume/release (Phase 2)
  Shared/          # genuinely cross-domain code only (e.g. TimestampableTrait)
  Controller/       # top-level pages with no dedicated domain yet (Home, Pricing, Legal)
  # Billing/, Extension/, Admin/ — Phase 3+

assets/app/src/
  entries/         # Vite entry points (one per React island)
  components/       # shadcn/ui primitives + layout components + conversion/ (ConvertHero)
  gps/              # shared client-side conversion engine (stubs until Phase 5/6)

templates/         # Twig — every public page
translations/       # Twig i18n catalogs (messages.{en,fr}.yaml)
migrations/         # Doctrine migrations
documentation/      # fonctionnel/ (product), technique/ (implementation), decisions/ (ADRs)
```

## Current architectural state (through Phase 2)

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

Everything else (Chrome extension, Stripe payments, free format tools, SEO content, admin) is
out of scope until later phases — see the implementation order in the product brief and the
phase notes scattered through `documentation/fonctionnel/*.md`.
