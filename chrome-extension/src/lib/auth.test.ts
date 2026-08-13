import { beforeEach, describe, expect, it, vi } from 'vitest';
import { clearStoredConnection, getStoredConnection, setStoredConnection } from './auth';

let storageData: Record<string, unknown>;

beforeEach(() => {
    storageData = {};

    vi.stubGlobal('chrome', {
        storage: {
            local: {
                get: (key: string) => Promise.resolve(key in storageData ? { [key]: storageData[key] } : {}),
                set: (items: Record<string, unknown>) => {
                    Object.assign(storageData, items);
                    return Promise.resolve();
                },
                remove: (key: string) => {
                    delete storageData[key];
                    return Promise.resolve();
                },
            },
        },
    });
});

describe('getStoredConnection', () => {
    it('returns null when nothing is stored', async () => {
        await expect(getStoredConnection()).resolves.toBeNull();
    });

    it('returns the stored connection after setStoredConnection', async () => {
        await setStoredConnection({ token: 'sgpx_ext_abc', apiOrigin: 'http://127.0.0.1:8000' });

        await expect(getStoredConnection()).resolves.toEqual({
            token: 'sgpx_ext_abc',
            apiOrigin: 'http://127.0.0.1:8000',
        });
    });

    it('returns null after clearStoredConnection', async () => {
        await setStoredConnection({ token: 'sgpx_ext_abc', apiOrigin: 'http://127.0.0.1:8000' });
        await clearStoredConnection();

        await expect(getStoredConnection()).resolves.toBeNull();
    });

    it('returns null for malformed stored data rather than throwing', async () => {
        storageData.connection = { token: 42 };

        await expect(getStoredConnection()).resolves.toBeNull();
    });
});
