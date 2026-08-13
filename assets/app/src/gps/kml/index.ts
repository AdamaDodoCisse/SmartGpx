import type { GpsRoute } from '../model';

// TODO(Phase 5) : implémenter le parseur/générateur KML (Point, LineString).

export function parseKml(_content: string): GpsRoute {
    throw new Error('parseKml: not implemented yet (Phase 5).');
}

export function generateKml(_route: GpsRoute): string {
    throw new Error('generateKml: not implemented yet (Phase 5).');
}
