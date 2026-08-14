/**
 * Petits utilitaires DOM partagés par gpx/ et kml/ — évite de dupliquer le même parsing XML
 * "brut" (DOMParser natif, pas de bibliothèque, voir ADR-003) dans les deux modules.
 */

export function parseXmlDocument(content: string, formatLabel: string): Document {
    const doc = new DOMParser().parseFromString(content, 'application/xml');
    const errorEl = doc.getElementsByTagName('parsererror')[0];

    if (undefined !== errorEl) {
        throw new Error(`${formatLabel}: XML invalide — ${errorEl.textContent ?? 'erreur inconnue'}.`);
    }

    return doc;
}

/** Enfants directs d'un élément par nom local, indépendamment du préfixe de namespace. */
export function directChildren(el: Element, localName: string): Element[] {
    return Array.from(el.children).filter((child) => child.localName === localName);
}

export function childText(el: Element, localName: string): string | undefined {
    const text = directChildren(el, localName)[0]?.textContent?.trim();

    return text ? text : undefined;
}
