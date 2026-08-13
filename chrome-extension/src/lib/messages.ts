export type TravelMode = 'DRIVE' | 'WALK' | 'BICYCLE' | 'TWO_WHEELER' | 'TRANSIT';

export interface AccountPayload {
    creditBalance: number;
    hasEverConverted: boolean;
}

export interface ConversionPayload {
    publicId: string;
    origin: string;
    destination: string;
    stops: string[];
    distanceMeters: number;
    durationSeconds: number;
    travelMode: TravelMode;
    travelModeInferred: boolean;
    trackPointCount: number;
    downloadUrl: string;
}

/** Messages the popup sends to the background service worker. */
export type ExtensionRequest =
    | { type: 'GET_ACCOUNT' }
    | { type: 'CONVERT'; googleMapsUrl: string }
    | { type: 'DOWNLOAD'; downloadUrl: string; suggestedFileName: string };

export type ExtensionResponse<T> =
    | { ok: true; data: T }
    | { ok: false; error: string; requiresReconnect: boolean };

/** Message the web app sends to the background worker via externally_connectable. */
export interface ConnectHandoffMessage {
    type: 'SMARTGPX_CONNECT';
    token: string;
    apiOrigin: string;
}

export type ConnectHandoffResponse = { ok: true } | { ok: false; error: string };

export function sendExtensionRequest<T>(request: ExtensionRequest): Promise<ExtensionResponse<T>> {
    return chrome.runtime.sendMessage(request) as Promise<ExtensionResponse<T>>;
}

export function isConnectHandoffMessage(value: unknown): value is ConnectHandoffMessage {
    return (
        null !== value &&
        'object' === typeof value &&
        'SMARTGPX_CONNECT' === (value as { type?: unknown }).type &&
        'string' === typeof (value as { token?: unknown }).token &&
        'string' === typeof (value as { apiOrigin?: unknown }).apiOrigin
    );
}
