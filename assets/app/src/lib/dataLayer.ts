declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}

/**
 * Pousse toujours dans window.dataLayer, sans jamais regarder si GTM est chargé — voir
 * documentation/technique/google-tag-manager.md. C'est le script du conteneur GTM lui-même
 * (base.html.twig) qui est conditionné au consentement, jamais ces appels : sans conteneur
 * chargé, un push reste un tableau local inoffensif, rien n'est envoyé à Google.
 */
export function pushToDataLayer(event: Record<string, unknown>): void {
    window.dataLayer = window.dataLayer ?? [];
    window.dataLayer.push(event);
}
