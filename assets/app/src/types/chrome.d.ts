/**
 * Typage minimal de la partie de l'API chrome.runtime exposée aux pages web ordinaires par les
 * extensions déclarant cette origine dans leur manifest.json (externally_connectable.matches) —
 * ce site n'est pas une extension Chrome, seul ce point d'API ponctuel est nécessaire, d'où un
 * typage local plutôt qu'une dépendance @types/chrome complète.
 */
interface ChromeRuntimeLastError {
    message?: string;
}

interface ChromeRuntime {
    sendMessage(
        extensionId: string,
        message: unknown,
        responseCallback?: (response: unknown) => void,
    ): void;
    readonly lastError?: ChromeRuntimeLastError;
}

interface ChromeGlobal {
    runtime?: ChromeRuntime;
}

declare const chrome: ChromeGlobal | undefined;
