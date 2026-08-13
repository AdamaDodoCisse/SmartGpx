import type { GpsRoute } from '../model';

// TODO(Phase 5) : implémenter la fusion de plusieurs GpsRoute (piste unique continue
// ou segments distincts préservés), en conservant les waypoints.

export type MergeMode = 'single-track' | 'separate-segments';

export function mergeRoutes(_routes: GpsRoute[], _mode: MergeMode): GpsRoute {
    throw new Error('mergeRoutes: not implemented yet (Phase 5).');
}
