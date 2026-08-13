import type { GpsRoute } from '../model';

// TODO(Phase 6) : implémenter l'extraction sécurisée du KML depuis une archive KMZ
// (garde-fous zip bomb / path traversal, voir documentation/technique/kml-kmz.md).

export function parseKmz(_content: ArrayBuffer): Promise<GpsRoute> {
    throw new Error('parseKmz: not implemented yet (Phase 6).');
}
