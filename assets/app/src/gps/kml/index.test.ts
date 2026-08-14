import { describe, expect, it } from 'vitest';
import { generateKml, parseKml } from './index';

const SAMPLE_KML = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <name>Start</name>
            <description>Departure point</description>
            <Point><coordinates>2.3522,48.8566,35</coordinates></Point>
        </Placemark>
        <Placemark>
            <name>Track 1</name>
            <LineString><coordinates>2.3522,48.8566,35 2.4,48.9 2.5,49.0</coordinates></LineString>
        </Placemark>
    </Document>
</kml>`;

describe('parseKml', () => {
    it('parses Point placemarks as waypoints and LineString placemarks as tracks', () => {
        const route = parseKml(SAMPLE_KML);

        expect(route.waypoints).toEqual([
            { longitude: 2.3522, latitude: 48.8566, elevation: 35, name: 'Start', description: 'Departure point' },
        ]);
        expect(route.tracks).toHaveLength(1);
        expect(route.tracks[0].name).toBe('Track 1');
        expect(route.tracks[0].points).toHaveLength(3);
        expect(route.routes).toEqual([]);
    });

    it('does not swap latitude and longitude (KML coordinate order is lon,lat)', () => {
        const kml = `<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>
            <Placemark><Point><coordinates>2.3522,48.8566</coordinates></Point></Placemark>
        </Document></kml>`;

        const route = parseKml(kml);

        expect(route.waypoints[0].longitude).toBe(2.3522);
        expect(route.waypoints[0].latitude).toBe(48.8566);
    });

    it('throws when the file has no point or track', () => {
        const kml = '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document></Document></kml>';

        expect(() => parseKml(kml)).toThrow();
    });

    it('throws on malformed XML', () => {
        expect(() => parseKml('<kml><unclosed>')).toThrow();
    });
});

describe('generateKml', () => {
    it('round-trips through parseKml with no loss', () => {
        const original = parseKml(SAMPLE_KML);

        const regenerated = parseKml(generateKml(original));

        expect(regenerated).toEqual(original);
    });

    it('folds both tracks and routes into LineString placemarks', () => {
        const route = {
            waypoints: [],
            tracks: [{ name: 'Track', points: [{ latitude: 1, longitude: 2 }] }],
            routes: [{ name: 'Route', points: [{ latitude: 3, longitude: 4 }] }],
        };

        const regenerated = parseKml(generateKml(route));

        expect(regenerated.tracks).toHaveLength(2);
        expect(regenerated.tracks.map((track) => track.name)).toEqual(['Track', 'Route']);
    });
});
