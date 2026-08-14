import type { GpsRoute } from '../model';

export type MergeMode = 'single-track' | 'separate-segments';

/**
 * Fusionne plusieurs GpsRoute en une seule. Les waypoints sont concaténés tels quels (pas de
 * préfixage anti-collision — un détail cosmétique, pas une priorité pour un premier outil
 * gratuit). Le nom résultant joint les noms sources non vides avec « + ».
 *
 * En sortie, routes est toujours vide : la distinction trk/rte n'a aucune valeur produit une
 * fois fusionnée dans un unique GPX téléchargeable.
 */
export function mergeRoutes(routes: GpsRoute[], mode: MergeMode): GpsRoute {
    const waypoints = routes.flatMap((route) => route.waypoints);
    const name = routes.map((route) => route.name).filter((n): n is string => Boolean(n)).join(' + ') || undefined;
    const allTracks = routes.flatMap((route) => [...route.tracks, ...route.routes]);

    if ('single-track' === mode) {
        return {
            name,
            waypoints,
            tracks: [{ name, points: allTracks.flatMap((track) => track.points) }],
            routes: [],
        };
    }

    return { name, waypoints, tracks: allTracks, routes: [] };
}
