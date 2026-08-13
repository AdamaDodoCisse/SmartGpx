import { StrictMode, type ReactElement } from 'react';
import { createRoot } from 'react-dom/client';
import '../i18n';

/**
 * Monte un composant React "island" dans un noeud du DOM rendu par Twig.
 * Chaque attribut data-* du noeud est transmis tel quel dans `props` sous forme de string.
 */
export function mountIsland(
    elementId: string,
    render: (props: Record<string, string>) => ReactElement,
): void {
    const element = document.getElementById(elementId);
    if (null === element) {
        return;
    }

    const props: Record<string, string> = { ...element.dataset } as Record<string, string>;

    createRoot(element).render(<StrictMode>{render(props)}</StrictMode>);
}
