import type { GpsPoint } from '../model';

// TODO(Phase 5) : implémenter Ramer-Douglas-Peucker avec tolérance configurable
// (voir documentation/decisions/ADR-003-browser-conversions.md).

export interface SimplifyOptions {
    toleranceMeters: number;
    maxPoints?: number;
}

export function simplifyTrack(_points: GpsPoint[], _options: SimplifyOptions): GpsPoint[] {
    throw new Error('simplifyTrack: not implemented yet (Phase 5).');
}
