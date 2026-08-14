import { describe, expect, it } from 'vitest';
import { generateGpx, parseGpx } from './index';

const SAMPLE_GPX = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Test" xmlns="http://www.topografix.com/GPX/1/1">
    <metadata><name>Morning Run</name></metadata>
    <wpt lat="48.8566" lon="2.3522">
        <ele>35</ele>
        <time>2026-01-01T08:00:00Z</time>
        <name>Start</name>
        <desc>Departure point</desc>
    </wpt>
    <trk>
        <name>Track 1</name>
        <trkseg>
            <trkpt lat="48.8566" lon="2.3522"><ele>35</ele></trkpt>
            <trkpt lat="48.9" lon="2.4"></trkpt>
        </trkseg>
        <trkseg>
            <trkpt lat="49.0" lon="2.5"></trkpt>
        </trkseg>
    </trk>
    <rte>
        <name>Route 1</name>
        <rtept lat="48.0" lon="2.0"></rtept>
        <rtept lat="48.1" lon="2.1"></rtept>
    </rte>
</gpx>`;

describe('parseGpx', () => {
    it('parses waypoints, tracks (flattening segments), and routes', () => {
        const route = parseGpx(SAMPLE_GPX);

        expect(route.name).toBe('Morning Run');
        expect(route.waypoints).toEqual([
            { latitude: 48.8566, longitude: 2.3522, elevation: 35, time: '2026-01-01T08:00:00Z', name: 'Start', description: 'Departure point' },
        ]);
        expect(route.tracks).toHaveLength(1);
        expect(route.tracks[0].name).toBe('Track 1');
        expect(route.tracks[0].points).toHaveLength(3);
        expect(route.routes).toHaveLength(1);
        expect(route.routes[0].name).toBe('Route 1');
        expect(route.routes[0].points).toHaveLength(2);
    });

    it('skips a malformed point but keeps parsing the rest', () => {
        const gpx = `<?xml version="1.0"?><gpx version="1.1" creator="Test" xmlns="http://www.topografix.com/GPX/1/1">
            <wpt lat="not-a-number" lon="2.0"></wpt>
            <wpt lat="48.0" lon="2.0"></wpt>
        </gpx>`;

        const route = parseGpx(gpx);

        expect(route.waypoints).toHaveLength(1);
    });

    it('throws when the file has no waypoint, track, or route', () => {
        const gpx = '<?xml version="1.0"?><gpx version="1.1" creator="Test" xmlns="http://www.topografix.com/GPX/1/1"></gpx>';

        expect(() => parseGpx(gpx)).toThrow();
    });

    it('throws on malformed XML', () => {
        expect(() => parseGpx('<gpx><unclosed>')).toThrow();
    });
});

describe('generateGpx', () => {
    it('round-trips through parseGpx with no loss', () => {
        const original = parseGpx(SAMPLE_GPX);

        const regenerated = parseGpx(generateGpx(original));

        expect(regenerated).toEqual(original);
    });

    it('omits an empty elevation but preserves elevation 0', () => {
        const route = {
            waypoints: [{ latitude: 1, longitude: 2, elevation: 0 }],
            tracks: [],
            routes: [],
        };

        const regenerated = parseGpx(generateGpx(route));

        expect(regenerated.waypoints[0].elevation).toBe(0);
    });
});
