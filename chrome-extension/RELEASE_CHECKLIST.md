# Release checklist

## Manual end-to-end verification

**Done** — full pass completed against a real Chrome browser, real Google Maps route, real
backend. One real bug found and fixed in the process: `ConnectPrompt.tsx` opened
`/account/extensions/connect` directly (the POST-only, CSRF-protected handoff-completion route)
instead of `/account/extensions` (the page carrying the actual "Connect a browser" button) —
clicking "connect your account" from a disconnected popup 405'd. Fixed by pointing the popup at
`/account/extensions` instead.

Automated coverage in `composer qa`/`npm run test` stops at `npm run typecheck` + `vitest` unit
tests for pure logic (`lib/mapsUrl.ts`, `lib/auth.ts`) and the backend's functional tests —
loading the real unpacked extension is possible via Playwright
(`chromium.launchPersistentContext` with `--load-extension`, see
`documentation/technique/chrome-extension.md`) and has been used repeatedly this way (the E2E pass
noted above, popup visual QA, generating the screenshots in `store-assets/`), but it's ad hoc
tooling re-run from a scratch script each time, not a committed regression test in this repo.
Steps kept below for the next time this needs re-running by hand (env changes, before a store
submission, etc.):

1. **Backend running locally**, reachable at `http://127.0.0.1:8000`.
2. `cd chrome-extension && npm install && npx vite build --mode development` — plain
   `npm run build` targets production (`PROD_API_ORIGIN`), which breaks `host_permissions` for
   local testing; the dev-mode build is what points the manifest at `127.0.0.1:8000`.
3. `chrome://extensions` → enable **Developer mode** → **Load unpacked** → select
   `chrome-extension/dist`. Note the generated extension ID.
4. Set `EXTENSION_CHROME_ID=<that ID>` in the backend's `.env.local`, clear its cache
   (`php bin/console cache:clear`).
5. Log into SmartGPX in a regular tab, go to `/account/extensions`, click **Connect a browser**.
   Confirm the handoff completes with **no copy-paste** — the page should show a success state
   on its own.
6. Open a real Google Maps directions page (e.g. any two-point route).
7. Click the SmartGPX toolbar icon. Confirm it shows the detected route (or at least recognizes
   you're on a directions page) and an Export button.
8. Click Export. Confirm:
   - A `.gpx` file lands in your downloads folder.
   - The file is valid GPX (opens in a GPX viewer, or `xmllint --noout` doesn't error).
   - The popup's credit line updates to reflect the spent credit.
9. Go back to `/account/extensions`, click **Revoke** on the connection just created.
10. Reopen the popup (or trigger another export) — confirm the extension immediately falls back
    to the "connect your account" state, with **no restart required**.
11. Confirm in DevTools → Network (on the extension's service worker) that no token, API key, or
    other secret ever appears in a console log, and that error responses only ever contain the
    generic translated error string.

## Before any real Chrome Web Store submission

None of the following is done yet — this project has only ever been loaded unpacked for local
development:

- [x] Replace the placeholder solid-color icons in `icons/` with real artwork — see
      `STORE_LISTING.md`'s "Icons" section.
- [ ] Replace `PROD_API_ORIGIN` in `src/lib/env.ts` (currently `https://smartgpx.com`, a
      placeholder) with the real production domain, once one exists.
- [x] Produce real screenshots for `STORE_LISTING.md` — done, see `store-assets/` and
      `STORE_LISTING.md`'s "Screenshots" section.
- [x] Write the full store listing copy (name/summary/description in EN+FR, category, single
      purpose statement, per-permission justifications, data-usage disclosure answers) — done,
      see `STORE_LISTING.md`. Ready to paste directly into the dashboard once the account exists.
- [ ] Remove any `CRX_DEV_PUBLIC_KEY` usage from your local environment — a production build
      must never embed a `key` field (see the "Stabilizing the extension ID" section in
      `README.md`); confirm `dist/manifest.json` has no `"key"` after a `npm run build` with
      `CRX_DEV_PUBLIC_KEY` unset.
- [ ] Register a Chrome Web Store developer account and complete the store's own privacy and
      permissions questionnaire, using `PRIVACY_DISCLOSURE.md` and `STORE_LISTING.md` as the
      source of truth.
- [x] Optional: generate promotional images (440×280 small tile, 1400×560 marquee) — done, see
      `store-assets/` and `STORE_LISTING.md`'s "Promotional images" section.
- [ ] Re-run the full manual end-to-end pass above against the production API origin, not
      `127.0.0.1`.
