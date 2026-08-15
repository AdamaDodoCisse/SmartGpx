import type { ReactNode } from 'react';

interface HeroCardProps {
    children: ReactNode;
}

/**
 * Carte partagée par tous les états du widget de conversion (formulaire, succès, crédits
 * épuisés, connexion requise, e-mail non vérifié) — même forme, même barre d'accent route en
 * haut, pour que les changements d'état se lisent comme un seul objet qui change de contenu
 * plutôt que des blocs d'UI différents qui apparaissent/disparaissent.
 */
export function HeroCard({ children }: HeroCardProps) {
    return (
        <div className="hero-card-scope relative mx-auto mt-8 max-w-xl overflow-hidden rounded-lg border border-border bg-background text-foreground shadow-lg">
            <div className="h-1 bg-route" aria-hidden="true" />
            <div className="p-6">{children}</div>
        </div>
    );
}
