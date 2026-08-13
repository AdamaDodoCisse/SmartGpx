import { convertGoogleMapsUrl, downloadAsDataUrl, ExtensionApiError, fetchAccount } from '@/lib/api';
import { clearStoredConnection, getStoredConnection, setStoredConnection } from '@/lib/auth';
import { isAllowedWebOrigin } from '@/lib/env';
import { isConnectHandoffMessage } from '@/lib/messages';
import type { ConnectHandoffResponse, ExtensionRequest, ExtensionResponse } from '@/lib/messages';

// The auth handoff from /account/extensions/connect: the web app calls
// chrome.runtime.sendMessage(extensionId, { type: 'SMARTGPX_CONNECT', token, apiOrigin }).
// sender.origin is checked against the same allowlist manifest.config.ts declares in
// externally_connectable — a page can only reach this listener if Chrome already restricted
// external senders to those origins, but re-checking here costs nothing and documents the
// invariant in the code that relies on it.
chrome.runtime.onMessageExternal.addListener((message: unknown, sender, sendResponse) => {
    if (!isConnectHandoffMessage(message) || undefined === sender.origin || !isAllowedWebOrigin(sender.origin)) {
        sendResponse({ ok: false, error: 'Rejected connection request.' } satisfies ConnectHandoffResponse);

        return false;
    }

    setStoredConnection({ token: message.token, apiOrigin: message.apiOrigin })
        .then(() => {
            sendResponse({ ok: true } satisfies ConnectHandoffResponse);
        })
        .catch(() => {
            sendResponse({ ok: false, error: 'Failed to store the connection.' } satisfies ConnectHandoffResponse);
        });

    return true;
});

// Service workers are non-persistent: nothing kept in module-level memory survives between
// events, so the token is re-read from chrome.storage.local on every popup request instead of
// being cached here.
chrome.runtime.onMessage.addListener((message: ExtensionRequest, _sender, sendResponse) => {
    handleRequest(message)
        .then(sendResponse)
        .catch((error: unknown) => {
            sendResponse({
                ok: false,
                error: error instanceof Error ? error.message : 'Unexpected error.',
                requiresReconnect: false,
            } satisfies ExtensionResponse<never>);
        });

    return true;
});

async function handleRequest(message: ExtensionRequest): Promise<ExtensionResponse<unknown>> {
    const connection = await getStoredConnection();

    if (null === connection) {
        return { ok: false, error: 'Not connected.', requiresReconnect: true };
    }

    try {
        switch (message.type) {
            case 'GET_ACCOUNT':
                return { ok: true, data: await fetchAccount(connection) };
            case 'CONVERT':
                return { ok: true, data: await convertGoogleMapsUrl(connection, message.googleMapsUrl) };
            case 'DOWNLOAD': {
                const dataUrl = await downloadAsDataUrl(connection, message.downloadUrl);
                const downloadId = await chrome.downloads.download({
                    url: dataUrl,
                    filename: message.suggestedFileName,
                    saveAs: false,
                });

                return { ok: true, data: { downloadId } };
            }
        }
    } catch (error) {
        if (error instanceof ExtensionApiError) {
            if (error.requiresReconnect) {
                await clearStoredConnection();
            }

            return { ok: false, error: error.message, requiresReconnect: error.requiresReconnect };
        }

        throw error;
    }
}
