import type { GpsRoute } from '../model';

// TODO(Phase 6) : s'appuyer sur une bibliothèque FIT mature existante plutôt que réimplémenter
// le protocole binaire FIT (voir documentation/technique/fit.md).

export function parseFit(_content: ArrayBuffer): Promise<GpsRoute> {
    throw new Error('parseFit: not implemented yet (Phase 6).');
}

export function generateFit(_route: GpsRoute): Promise<ArrayBuffer> {
    throw new Error('generateFit: not implemented yet (Phase 6).');
}
