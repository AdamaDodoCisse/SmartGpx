import { describe, expect, it } from 'vitest';
import { generateTcx, parseTcx } from './index';

const SAMPLE_TCX = `<?xml version="1.0" encoding="UTF-8"?>
<TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
    <Activities>
        <Activity Sport="Running">
            <Id>2026-01-01T08:00:00Z</Id>
            <Lap StartTime="2026-01-01T08:00:00Z">
                <Track>
                    <Trackpoint>
                        <Time>2026-01-01T08:00:00Z</Time>
                        <Position><LatitudeDegrees>48.8566</LatitudeDegrees><LongitudeDegrees>2.3522</LongitudeDegrees></Position>
                        <AltitudeMeters>35</AltitudeMeters>
                    </Trackpoint>
                    <Trackpoint>
                        <Time>2026-01-01T08:01:00Z</Time>
                        <Position><LatitudeDegrees>48.9</LatitudeDegrees><LongitudeDegrees>2.4</LongitudeDegrees></Position>
                    </Trackpoint>
                </Track>
            </Lap>
            <Lap StartTime="2026-01-01T08:02:00Z">
                <Track>
                    <Trackpoint>
                        <Time>2026-01-01T08:02:00Z</Time>
                        <Position><LatitudeDegrees>49.0</LatitudeDegrees><LongitudeDegrees>2.5</LongitudeDegrees></Position>
                    </Trackpoint>
                </Track>
            </Lap>
        </Activity>
    </Activities>
</TrainingCenterDatabase>`;

describe('parseTcx', () => {
    it('flattens laps within one activity into a single track', () => {
        const route = parseTcx(SAMPLE_TCX);

        expect(route.tracks).toHaveLength(1);
        expect(route.tracks[0].points).toHaveLength(3);
        expect(route.tracks[0].name).toBe('Running');
        expect(route.waypoints).toEqual([]);
        expect(route.routes).toEqual([]);
    });

    it('produces one track per activity for multiple activities', () => {
        const tcx = `<?xml version="1.0"?><TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
            <Activities>
                <Activity Sport="Running"><Id>a</Id><Lap StartTime="t"><Track><Trackpoint><Position><LatitudeDegrees>1</LatitudeDegrees><LongitudeDegrees>2</LongitudeDegrees></Position></Trackpoint></Track></Lap></Activity>
                <Activity Sport="Biking"><Id>b</Id><Lap StartTime="t"><Track><Trackpoint><Position><LatitudeDegrees>3</LatitudeDegrees><LongitudeDegrees>4</LongitudeDegrees></Position></Trackpoint></Track></Lap></Activity>
            </Activities>
        </TrainingCenterDatabase>`;

        const route = parseTcx(tcx);

        expect(route.tracks).toHaveLength(2);
        expect(route.tracks.map((t) => t.name)).toEqual(['Running', 'Biking']);
    });

    it('ignores a trackpoint with no position', () => {
        const tcx = `<?xml version="1.0"?><TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
            <Activities><Activity Sport="Running"><Id>a</Id><Lap StartTime="t"><Track>
                <Trackpoint><HeartRateBpm><Value>140</Value></HeartRateBpm></Trackpoint>
                <Trackpoint><Position><LatitudeDegrees>1</LatitudeDegrees><LongitudeDegrees>2</LongitudeDegrees></Position></Trackpoint>
            </Track></Lap></Activity></Activities>
        </TrainingCenterDatabase>`;

        const route = parseTcx(tcx);

        expect(route.tracks[0].points).toHaveLength(1);
    });

    it('throws when there is no trace at all', () => {
        const tcx = '<?xml version="1.0"?><TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"><Activities></Activities></TrainingCenterDatabase>';

        expect(() => parseTcx(tcx)).toThrow();
    });

    it('throws on malformed XML', () => {
        expect(() => parseTcx('<TrainingCenterDatabase><unclosed>')).toThrow();
    });
});

describe('generateTcx', () => {
    it('round-trips through parseTcx with no loss', () => {
        const original = parseTcx(SAMPLE_TCX);

        const regenerated = parseTcx(generateTcx(original));

        expect(regenerated).toEqual(original);
    });

    it('silently drops waypoints without throwing', () => {
        const route = { waypoints: [{ latitude: 1, longitude: 2, name: 'Orphan' }], tracks: [{ points: [{ latitude: 3, longitude: 4 }] }], routes: [] };

        const output = generateTcx(route);
        const regenerated = parseTcx(output);

        expect(regenerated.waypoints).toEqual([]);
        expect(output).not.toContain('Orphan');
    });
});
