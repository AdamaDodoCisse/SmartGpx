import type { GpsRoute } from '../model';

// TODO(Phase 6) : implémenter le parseur/générateur TCX.

export function parseTcx(_content: string): GpsRoute {
    throw new Error('parseTcx: not implemented yet (Phase 6).');
}

export function generateTcx(_route: GpsRoute): string {
    throw new Error('generateTcx: not implemented yet (Phase 6).');
}
