import type { GpsRoute } from '../model';

// TODO(Phase 5) : implémenter le parseur/générateur GPX (trk, trkseg, rte, wpt).

export function parseGpx(_content: string): GpsRoute {
    throw new Error('parseGpx: not implemented yet (Phase 5).');
}

export function generateGpx(_route: GpsRoute): string {
    throw new Error('generateGpx: not implemented yet (Phase 5).');
}
