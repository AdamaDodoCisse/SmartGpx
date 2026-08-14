import { Decoder, Encoder, Profile, Stream } from '@garmin/fitsdk';
import type { Encodable, FileIdMesg, RecordMesg } from '@garmin/fitsdk';
import { describe, expect, it } from 'vitest';
import { generateFit, parseFit } from './index';

const SAMPLE_POINTS = [
    { latitude: 48.8566, longitude: 2.3522, elevation: 35, time: '2026-01-01T08:00:00.000Z' },
    { latitude: 48.9, longitude: 2.4, time: '2026-01-01T08:01:00.000Z' },
    { latitude: 49.0, longitude: 2.5, time: '2026-01-01T08:02:00.000Z' },
];

describe('generateFit + parseFit', () => {
    it('round-trips known points within semicircle-quantization precision', async () => {
        // La fixture est construite avec le propre Encoder du SDK plutôt que sourcée d'un vrai
        // fichier .fit d'appareil — évite de committer un binaire, et prouve exactement le même
        // aller-retour qu'un utilisateur réel de l'outil GPX → FIT → GPX vivrait.
        const buffer = await generateFit({ waypoints: [], tracks: [{ points: SAMPLE_POINTS }], routes: [] });

        const route = await parseFit(buffer);

        expect(route.tracks[0].points).toHaveLength(SAMPLE_POINTS.length);
        route.tracks[0].points.forEach((point, i) => {
            // Quantification "semicircles" : perte de précision à la ~8e décimale, documentée,
            // pas un bug — on compare donc avec une tolérance, pas une égalité exacte.
            expect(point.latitude).toBeCloseTo(SAMPLE_POINTS[i].latitude, 6);
            expect(point.longitude).toBeCloseTo(SAMPLE_POINTS[i].longitude, 6);
        });
        expect(route.tracks[0].points[0].elevation).toBe(35);
    });

    it('produces a structurally valid FIT file (clean integrity check, no decode errors)', async () => {
        const buffer = await generateFit({ waypoints: [], tracks: [{ points: SAMPLE_POINTS }], routes: [] });

        const stream = Stream.fromByteArray(new Uint8Array(buffer));
        expect(Decoder.isFIT(stream)).toBe(true);

        const decoder = new Decoder(stream);
        expect(decoder.checkIntegrity()).toBe(true);

        const { errors } = decoder.read();
        expect(errors).toEqual([]);
    });

    it('throws when there are no points to export', async () => {
        await expect(generateFit({ waypoints: [], tracks: [], routes: [] })).rejects.toThrow();
    });

    it('falls back to the first route when there is no track', async () => {
        const buffer = await generateFit({ waypoints: [], tracks: [], routes: [{ points: SAMPLE_POINTS.slice(0, 1) }] });

        const route = await parseFit(buffer);

        expect(route.tracks[0].points).toHaveLength(1);
    });
});

describe('parseFit', () => {
    it('rejects a stream that is not a FIT file at all', async () => {
        const notFit = new TextEncoder().encode('this is not a fit file');

        await expect(parseFit(notFit.buffer)).rejects.toThrow();
    });

    it('throws when the FIT file has records but no GPS position (e.g. an indoor trainer file)', async () => {
        // On encode un fichier FIT valide dont le seul RECORD ne porte pas de position — les
        // capteurs d'intérieur (home trainer, cadence seule) produisent ce genre de fichier.
        const encoder = new Encoder();
        const fileIdMesg: Encodable<FileIdMesg> = { mesgNum: Profile.MesgNum.FILE_ID, type: 4, manufacturer: 255, product: 0, timeCreated: new Date() };
        encoder.writeMesg(fileIdMesg);
        const recordMesg: Encodable<RecordMesg> = { mesgNum: Profile.MesgNum.RECORD, timestamp: new Date(), heartRate: 140 };
        encoder.writeMesg(recordMesg);
        const bytes = encoder.close();
        const buffer = new ArrayBuffer(bytes.byteLength);
        new Uint8Array(buffer).set(bytes);

        await expect(parseFit(buffer)).rejects.toThrow();
    });
});
