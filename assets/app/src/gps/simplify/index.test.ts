import { describe, expect, it } from 'vitest';
import type { GpsPoint } from '../model';
import { simplifyTrack } from './index';

function collinearLine(count: number): GpsPoint[] {
    return Array.from({ length: count }, (_, i) => ({ latitude: 48.0, longitude: 2.0 + i * 0.001 }));
}

function lShapedTrack(): GpsPoint[] {
    // Segment A : latitude constante, longitude croissante (11 points colinéaires).
    const segmentA = Array.from({ length: 11 }, (_, i) => ({ latitude: 48.0, longitude: 2.0 + i * 0.001 }));
    // Coin à l'index 10 (dernier point de A), puis segment B perpendiculaire (colinéaire aussi).
    const segmentB = [48.005, 48.01, 48.015, 48.02].map((latitude) => ({ latitude, longitude: 2.01 }));

    return [...segmentA, ...segmentB];
}

describe('simplifyTrack', () => {
    it('returns the input unchanged when it has 2 or fewer points', () => {
        const points: GpsPoint[] = [{ latitude: 0, longitude: 0 }];
        expect(simplifyTrack(points, { toleranceMeters: 10 })).toBe(points);
    });

    it('collapses a straight line of collinear points to its two endpoints at any nonzero tolerance', () => {
        const points = collinearLine(20);

        const result = simplifyTrack(points, { toleranceMeters: 1 });

        expect(result).toEqual([points[0], points[19]]);
    });

    it('preserves a real corner while dropping near-collinear points on either side', () => {
        const points = lShapedTrack();

        const result = simplifyTrack(points, { toleranceMeters: 5 });

        expect(result).toEqual([points[0], points[10], points[14]]);
    });

    it('enforces maxPoints as a hard ceiling even at tolerance 0, keeping first and last points', () => {
        const points = lShapedTrack();

        const result = simplifyTrack(points, { toleranceMeters: 0, maxPoints: 2 });

        expect(result).toHaveLength(2);
        expect(result[0]).toEqual(points[0]);
        expect(result[1]).toEqual(points[points.length - 1]);
    });

    it('does not cap below maxPoints when the simplified result already fits', () => {
        const points = lShapedTrack();

        const result = simplifyTrack(points, { toleranceMeters: 5, maxPoints: 10 });

        expect(result).toHaveLength(3);
    });
});
