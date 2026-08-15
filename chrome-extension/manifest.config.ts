import { defineManifest } from '@crxjs/vite-plugin';
import { DEV_API_ORIGIN, PROD_API_ORIGIN } from './src/lib/env.ts';

const ICONS = {
    16: 'icons/icon16.png',
    32: 'icons/icon32.png',
    48: 'icons/icon48.png',
    128: 'icons/icon128.png',
};

// Optional, local-only: generate a real RSA keypair (`openssl genrsa 2048 | openssl rsa
// -pubout -outform DER | openssl base64 -A`) and export its public half as CRX_DEV_PUBLIC_KEY
// to get a stable unpacked extension ID across reloads — needed for externally_connectable
// testing against a fixed ID. Never committed; see chrome-extension/README.md.
const devPublicKey = process.env.CRX_DEV_PUBLIC_KEY;

export default defineManifest(({ mode }) => {
    const isDev = 'development' === mode;
    const apiOrigin = isDev ? DEV_API_ORIGIN : PROD_API_ORIGIN;

    return {
        manifest_version: 3,
        name: 'SmartGPX – Google Maps to GPX',
        description: 'Convert any Google Maps route to a GPX file in one click: works with Garmin, Wahoo, OsmAnd, Strava, and more.',
        version: '1.0.0',
        icons: ICONS,
        action: {
            default_popup: 'src/popup/index.html',
            default_icon: ICONS,
        },
        background: {
            service_worker: 'src/background/service-worker.ts',
            type: 'module',
        },
        // No host_permissions for google.* and no content script: the popup reads the current
        // tab's URL on demand via chrome.tabs.query, granted only on toolbar-icon click by
        // activeTab. host_permissions is scoped to exactly the SmartGPX API origin, needed by
        // the background worker's fetch() calls.
        permissions: ['activeTab', 'storage', 'downloads'],
        host_permissions: [`${apiOrigin}/*`],
        // Lets the web app's /account/extensions/connect page hand a fresh token to this
        // extension via chrome.runtime.sendMessage — no OAuth flow, no copy-paste.
        externally_connectable: {
            matches: [`${DEV_API_ORIGIN}/*`, `${PROD_API_ORIGIN}/*`],
        },
        ...(isDev && devPublicKey ? { key: devPublicKey } : {}),
    };
});
