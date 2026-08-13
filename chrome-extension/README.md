# SmartGPX — Chrome Extension

Manifest V3 extension that exports the Google Maps route open in the current tab to GPX,
without leaving Google Maps. See `documentation/technique/chrome-extension.md` in the repo root
for the full architecture write-up, and
`documentation/decisions/ADR-005-extension-authentication.md` for why authentication works the
way it does.

## Requirements

- Node.js (matching the version used by `assets/app/`)
- A running SmartGPX backend (`php bin/console server:run`, or your usual local setup) reachable
  at `http://127.0.0.1:8000` in development.

## Setup

```bash
npm install
```

Set `EXTENSION_CHROME_ID` in the backend's `.env.local` to the extension's ID once you've loaded
it unpacked (see below) — `/account/extensions/connect` uses it to target the right extension
with `chrome.runtime.sendMessage`.

## Development

```bash
npm run dev      # Vite dev server with HMR for the popup
npm run build    # production build into dist/
npm run typecheck
npm run test     # vitest unit tests (lib/mapsUrl, lib/auth)
```

### Loading the extension locally

1. `npm run build` (or `npm run dev` — `@crxjs/vite-plugin` supports loading the dev build
   unpacked too).
2. Open `chrome://extensions`, enable **Developer mode**.
3. **Load unpacked** → select `chrome-extension/dist`.
4. Copy the generated extension ID into the backend's `EXTENSION_CHROME_ID` env var and restart
   the backend (or clear its cache: `php bin/console cache:clear`).

### Stabilizing the extension ID across reloads

Chrome normally assigns a new unpacked extension ID every time `dist/` is regenerated from
scratch, which breaks the `externally_connectable` handoff until you update
`EXTENSION_CHROME_ID` again. To avoid that during active development, generate a local keypair
and export its public half:

```bash
openssl genrsa 2048 | openssl rsa -pubout -outform DER | openssl base64 -A
export CRX_DEV_PUBLIC_KEY="<paste the output above>"
npm run build
```

`manifest.config.ts` only embeds `key` in development builds, and only when
`CRX_DEV_PUBLIC_KEY` is set — never commit a real key, and never ship it in a production build.

## Project layout

```
manifest.config.ts     Manifest V3, branches host_permissions/externally_connectable by mode
src/popup/              Popup UI (React) — shown on toolbar icon click
src/background/         Service worker — the only code that ever reads the stored token
src/lib/                env, auth (storage), api (fetch), mapsUrl (URL matching), messages, i18n
icons/                  Placeholder PNGs — see STORE_LISTING.md before any real submission
```

## Before submitting to the Chrome Web Store

See `RELEASE_CHECKLIST.md` — this is not done yet (see there for what's missing: real icons,
screenshots, a production `PROD_API_ORIGIN`, and the manual end-to-end pass).
