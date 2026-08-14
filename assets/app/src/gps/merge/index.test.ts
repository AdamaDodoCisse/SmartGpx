import { describe, expect, it } from 'vitest';
import type { GpsRoute } from '../model';
import { mergeRoutes } from './index';

function route(name: string, trackPoints: number, routePoints: number, waypoints: number): GpsRoute {
    return {
        name,
        waypoints: Array.from({ length: waypoints }, (_, i) => ({ latitude: i, longitude: i, name: `${name}-wpt-${i}` })),
        tracks: [{ name: `${name}-track`, points: Array.from({ length: trackPoints }, (_, i) => ({ latitude: i, longitude: i })) }],
        routes: [{ name: `${name}-route`, points: Array.from({ length: routePoints }, (_, i) => ({ latitude: i, longitude: i })) }],
    };
}

describe('mergeRoutes', () => {
    it('single-track: flattens every track and route into one continuous track', () => {
        const merged = mergeRoutes([route('A', 3, 2, 1), route('B', 2, 1, 1)], 'single-track');

        expect(merged.tracks).toHaveLength(1);
        expect(merged.tracks[0].points).toHaveLength(3 + 2 + 2 + 1);
        expect(merged.routes).toEqual([]);
    });

    it('separate-segments: keeps each source track/route as its own entry', () => {
        const merged = mergeRoutes([route('A', 3, 2, 1), route('B', 2, 1, 1)], 'separate-segments');

        expect(merged.tracks).toHaveLength(4); // A-track, A-route, B-track, B-route
        expect(merged.tracks.map((t) => t.points.length)).toEqual([3, 2, 2, 1]);
        expect(merged.routes).toEqual([]);
    });

    it('concatenates waypoints from every input, unmodified', () => {
        const merged = mergeRoutes([route('A', 1, 1, 2), route('B', 1, 1, 3)], 'single-track');

        expect(merged.waypoints).toHaveLength(5);
        expect(merged.waypoints.map((w) => w.name)).toEqual(['A-wpt-0', 'A-wpt-1', 'B-wpt-0', 'B-wpt-1', 'B-wpt-2']);
    });

    it('joins non-empty source names with " + "', () => {
        const merged = mergeRoutes([route('Morning Run', 1, 1, 0), route('Evening Run', 1, 1, 0)], 'single-track');

        expect(merged.name).toBe('Morning Run + Evening Run');
    });

    it('falls back to undefined when no input route has a name', () => {
        const unnamed: GpsRoute = { waypoints: [], tracks: [], routes: [] };

        const merged = mergeRoutes([unnamed, unnamed], 'single-track');

        expect(merged.name).toBeUndefined();
    });
});
