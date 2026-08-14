import type { JSX, SVGProps } from 'react';

// Tracés extraits de lucide-react (déjà une dépendance du projet principal, licence ISC) —
// composants React locaux plutôt qu'une dépendance lucide-react ajoutée à ce build séparé,
// vu le nombre réduit d'icônes réellement utilisées dans la popup.
function createIcon(children: JSX.Element) {
    return function Icon(props: SVGProps<SVGSVGElement>) {
        return (
            <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                className="icon"
                {...props}
            >
                {children}
            </svg>
        );
    };
}

export const RouteIcon = createIcon(
    <>
        <circle cx="6" cy="19" r="3" />
        <path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15" />
        <circle cx="18" cy="5" r="3" />
    </>,
);

export const MapPinIcon = createIcon(
    <>
        <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
        <circle cx="12" cy="10" r="3" />
    </>,
);

export const DownloadIcon = createIcon(
    <>
        <path d="M12 15V3" />
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <path d="m7 10 5 5 5-5" />
    </>,
);

export const CircleCheckIcon = createIcon(
    <>
        <circle cx="12" cy="12" r="10" />
        <path d="m9 12 2 2 4-4" />
    </>,
);

export const CircleAlertIcon = createIcon(
    <>
        <circle cx="12" cy="12" r="10" />
        <line x1="12" x2="12" y1="8" y2="12" />
        <line x1="12" x2="12.01" y1="16" y2="16" />
    </>,
);

export const PuzzleIcon = createIcon(
    <path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z" />,
);

// Marque SmartGPX : anneau topographique blanc ouvert + point de repère orange (--route) sur
// fond vert pin (--primary) — badge autoportant à couleurs fixes, pas un pictogramme
// currentColor comme createIcon() ci-dessus, donc composant à part plutôt que via la factory.
export function LogoMark(props: SVGProps<SVGSVGElement>) {
    return (
        <svg width="22" height="22" viewBox="0 0 128 128" role="img" aria-label="SmartGPX" {...props}>
            <rect width="128" height="128" rx="28" fill="#003f1d" />
            <path d="M 90 44 A 34 34 0 1 0 62 90" fill="none" stroke="#f8fbf9" strokeWidth="12" strokeLinecap="round" />
            <circle cx="90" cy="44" r="14" fill="#e36400" />
        </svg>
    );
}
