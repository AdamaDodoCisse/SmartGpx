# Privacy disclosure

Required background for the Chrome Web Store's privacy practices tab. Each requested permission
is justified individually below, per Google's single-purpose and minimal-permissions policies.

## What this extension does

Exports the Google Maps route open in your current browser tab to a GPX file, using your
SmartGPX account's credits. Nothing more.

## Permissions requested and why

| Permission | Why |
|---|---|
| `activeTab` | Lets the popup read the URL of the tab you're currently viewing, but **only** when you click the toolbar icon — not continuously, and not for any tab you haven't actively opened the popup on. This is how the extension knows you're looking at a Google Maps route, without a content script and without `host_permissions` for `google.*`. |
| `storage` | Stores your SmartGPX authorization token locally (`chrome.storage.local`), so you don't have to reconnect every time you open the popup. |
| `downloads` | The only Manifest V3 mechanism available to a service worker for saving a file to disk — used exactly once per export, to save the generated `.gpx` file. |
| `host_permissions` (SmartGPX API origin only) | Lets the background service worker call the SmartGPX API (`fetch`) to convert routes and check your credit balance. Scoped to exactly the SmartGPX API domain — no other site is ever contacted. |

## What this extension does **not** do

- No access to your browsing history.
- No content script — it never reads or modifies any Google Maps page automatically; it only
  reads the current tab's URL on explicit click.
- No site other than the SmartGPX API is ever contacted.
- No data is sold, shared with third parties, or used for advertising.
- No password is stored — only a revocable authorization token, which you can revoke at any
  time from `/account/extensions` without changing your account password.

## Data retained

Your SmartGPX authorization token and the SmartGPX API origin, stored locally in
`chrome.storage.local` on your device. Revoking the connection from your SmartGPX account
invalidates the token on the server immediately; uninstalling the extension clears local storage.
