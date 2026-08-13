/**
 * The API origin isn't hardcoded at runtime: it's handed over by the web app during the
 * connect handoff (see AccountExtensionController::connect()) and persisted alongside the
 * token in chrome.storage.local. These constants exist only for build-time concerns —
 * manifest.config.ts's host_permissions/externally_connectable — and as the default shown
 * before a connection exists.
 */
export const DEV_API_ORIGIN = 'http://127.0.0.1:8000';

// Placeholder — replace with the real production domain before any Chrome Web Store
// submission. See chrome-extension/RELEASE_CHECKLIST.md.
export const PROD_API_ORIGIN = 'https://smartgpx.com';

export const ALLOWED_WEB_ORIGINS: readonly string[] = [DEV_API_ORIGIN, PROD_API_ORIGIN];

export function isAllowedWebOrigin(origin: string): boolean {
    return ALLOWED_WEB_ORIGINS.includes(origin);
}
