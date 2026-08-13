export interface StoredConnection {
    token: string;
    apiOrigin: string;
}

const STORAGE_KEY = 'connection';

export async function getStoredConnection(): Promise<StoredConnection | null> {
    const result = await chrome.storage.local.get(STORAGE_KEY);
    const value: unknown = result[STORAGE_KEY];

    return isStoredConnection(value) ? value : null;
}

export async function setStoredConnection(connection: StoredConnection): Promise<void> {
    await chrome.storage.local.set({ [STORAGE_KEY]: connection });
}

export async function clearStoredConnection(): Promise<void> {
    await chrome.storage.local.remove(STORAGE_KEY);
}

function isStoredConnection(value: unknown): value is StoredConnection {
    return (
        null !== value &&
        'object' === typeof value &&
        'string' === typeof (value as { token?: unknown }).token &&
        'string' === typeof (value as { apiOrigin?: unknown }).apiOrigin
    );
}
