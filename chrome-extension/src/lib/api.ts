import type { StoredConnection } from './auth';
import type { AccountPayload, ConversionPayload } from './messages';

export class ExtensionApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ExtensionApiError';
    }

    get requiresReconnect(): boolean {
        return 401 === this.status;
    }
}

async function requestJson<T>(connection: StoredConnection, path: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(`${connection.apiOrigin}${path}`, {
        ...init,
        headers: {
            ...init.headers,
            Authorization: `Bearer ${connection.token}`,
        },
    });

    if (!response.ok) {
        throw new ExtensionApiError(await readErrorMessage(response), response.status);
    }

    return response.json() as Promise<T>;
}

async function readErrorMessage(response: Response): Promise<string> {
    const body: unknown = await response.json().catch(() => null);
    const error = null !== body && 'object' === typeof body ? (body as { error?: unknown }).error : null;

    return 'string' === typeof error ? error : `Request failed with status ${response.status}.`;
}

export function fetchAccount(connection: StoredConnection): Promise<AccountPayload> {
    return requestJson<AccountPayload>(connection, '/api/extension/account');
}

export function convertGoogleMapsUrl(connection: StoredConnection, googleMapsUrl: string): Promise<ConversionPayload> {
    return requestJson<ConversionPayload>(connection, '/api/extension/conversions/google-maps', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: googleMapsUrl }),
    });
}

export async function downloadAsDataUrl(connection: StoredConnection, downloadUrl: string): Promise<string> {
    const response = await fetch(`${connection.apiOrigin}${downloadUrl}`, {
        headers: { Authorization: `Bearer ${connection.token}` },
    });

    if (!response.ok) {
        throw new ExtensionApiError(await readErrorMessage(response), response.status);
    }

    return blobToDataUrl(await response.blob());
}

// Not URL.createObjectURL: the object URL it returns is only valid in the document that created
// it, which is unreliable once a non-persistent background service worker is torn down between
// the fetch and chrome.downloads.download() picking it up. A base64 data URL has no such lifetime
// tied to a specific execution context.
function blobToDataUrl(blob: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            if ('string' === typeof reader.result) {
                resolve(reader.result);
            } else {
                reject(new Error('Failed to read the downloaded file.'));
            }
        };
        reader.onerror = () => {
            reject(reader.error ?? new Error('Failed to read the downloaded file.'));
        };
        reader.readAsDataURL(blob);
    });
}
