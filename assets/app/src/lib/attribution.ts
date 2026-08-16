const STORAGE_KEY = 'smartgpx_landing_page';

/**
 * Attribution volontairement minimale (voir documentation/technique/google-tag-manager.md) : une
 * seule clé localStorage, jamais expirée/effacée. Un achat s'attribue toujours à la dernière page
 * de guide visitée, pour toujours — suffisant pour comparer les niches Garmin/Wahoo/OsmAnd entre
 * elles, pas la peine d'un vrai système d'attribution marketing pour ça.
 */
export function setLandingPage(value: string): void {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Stockage indisponible (navigation privée stricte, quota…) : le tracking se dégrade sans
        // landing_page, jamais une erreur bloquante pour le convertisseur.
    }
}

export function getLandingPage(): string | undefined {
    try {
        return localStorage.getItem(STORAGE_KEY) ?? undefined;
    } catch {
        return undefined;
    }
}
