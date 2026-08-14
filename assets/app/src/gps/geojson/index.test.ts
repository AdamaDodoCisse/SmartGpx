import { describe, expect, it } from 'vitest';
import { generateGeoJson, parseGeoJson } from './index';

const SAMPLE_FEATURE_COLLECTION = JSON.stringify({
    type: 'FeatureCollection',
    features: [
        {
            type: 'Feature',
            properties: { name: 'Start', description: 'Departure point' },
            geometry: { type: 'Point', coordinates: [2.3522, 48.8566, 35] },
        },
        {
            type: 'Feature',
            properties: { name: 'Track 1' },
            geometry: {
                type: 'LineString',
                coordinates: [
                    [2.3522, 48.8566],
                    [2.4, 48.9],
                    [2.5, 49.0],
                ],
            },
        },
    ],
});

describe('parseGeoJson', () => {
    it('parses Point features as waypoints and LineString features as tracks', () => {
        const route = parseGeoJson(SAMPLE_FEATURE_COLLECTION);

        expect(route.waypoints).toEqual([
            { longitude: 2.3522, latitude: 48.8566, elevation: 35, name: 'Start', description: 'Departure point' },
        ]);
        expect(route.tracks).toHaveLength(1);
        expect(route.tracks[0].name).toBe('Track 1');
        expect(route.tracks[0].points).toHaveLength(3);
        expect(route.routes).toEqual([]);
    });

    it('does not swap latitude and longitude (GeoJSON coordinate order is lon,lat)', () => {
        const geojson = JSON.stringify({
            type: 'Feature',
            properties: {},
            geometry: { type: 'Point', coordinates: [2.3522, 48.8566] },
        });

        const route = parseGeoJson(geojson);

        expect(route.waypoints[0].longitude).toBe(2.3522);
        expect(route.waypoints[0].latitude).toBe(48.8566);
    });

    it('accepts a bare Feature (not wrapped in a FeatureCollection)', () => {
        const geojson = JSON.stringify({
            type: 'Feature',
            properties: { name: 'Solo' },
            geometry: { type: 'Point', coordinates: [1, 2] },
        });

        const route = parseGeoJson(geojson);

        expect(route.waypoints).toHaveLength(1);
        expect(route.waypoints[0].name).toBe('Solo');
    });

    it('accepts a bare geometry (not wrapped in a Feature)', () => {
        const geojson = JSON.stringify({ type: 'Point', coordinates: [1, 2] });

        const route = parseGeoJson(geojson);

        expect(route.waypoints).toHaveLength(1);
    });

    it('splits a MultiLineString into multiple tracks, preserving segment boundaries', () => {
        const geojson = JSON.stringify({
            type: 'Feature',
            properties: { name: 'Multi' },
            geometry: {
                type: 'MultiLineString',
                coordinates: [
                    [[1, 2], [3, 4]],
                    [[5, 6], [7, 8], [9, 10]],
                ],
            },
        });

        const route = parseGeoJson(geojson);

        expect(route.tracks).toHaveLength(2);
        expect(route.tracks[0].points).toHaveLength(2);
        expect(route.tracks[1].points).toHaveLength(3);
    });

    it('silently skips unsupported geometry types', () => {
        const geojson = JSON.stringify({
            type: 'FeatureCollection',
            features: [
                { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [[[0, 0], [1, 1], [1, 0], [0, 0]]] } },
                { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: [1, 2] } },
            ],
        });

        const route = parseGeoJson(geojson);

        expect(route.waypoints).toHaveLength(1);
        expect(route.tracks).toHaveLength(0);
    });

    it('throws when nothing usable is found', () => {
        const geojson = JSON.stringify({
            type: 'FeatureCollection',
            features: [{ type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [] } }],
        });

        expect(() => parseGeoJson(geojson)).toThrow();
    });

    it('throws on unrecognized structure', () => {
        expect(() => parseGeoJson(JSON.stringify({ foo: 'bar' }))).toThrow();
    });
});

describe('generateGeoJson', () => {
    it('round-trips through parseGeoJson with no loss', () => {
        const original = parseGeoJson(SAMPLE_FEATURE_COLLECTION);

        const regenerated = parseGeoJson(generateGeoJson(original));

        expect(regenerated).toEqual(original);
    });

    it('always emits a FeatureCollection', () => {
        const route = { waypoints: [{ latitude: 1, longitude: 2 }], tracks: [], routes: [] };

        const output: unknown = JSON.parse(generateGeoJson(route));

        expect(output).toMatchObject({ type: 'FeatureCollection' });
    });

    it('folds tracks and routes into LineString features', () => {
        const route = {
            waypoints: [],
            tracks: [{ name: 'Track', points: [{ latitude: 1, longitude: 2 }] }],
            routes: [{ name: 'Route', points: [{ latitude: 3, longitude: 4 }] }],
        };

        const regenerated = parseGeoJson(generateGeoJson(route));

        expect(regenerated.tracks).toHaveLength(2);
    });
});
