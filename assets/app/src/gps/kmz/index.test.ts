import { zipSync } from 'fflate';
import { describe, expect, it } from 'vitest';
import { parseKml } from '../kml';
import { parseKmz } from './index';

const SAMPLE_KML = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark><name>Start</name><Point><coordinates>2.3522,48.8566</coordinates></Point></Placemark>
        <Placemark><name>Track A</name><LineString><coordinates>2.3522,48.8566 2.4,48.9</coordinates></LineString></Placemark>
    </Document>
</kml>`;

function zipOf(entries: Record<string, Uint8Array>): ArrayBuffer {
    const zipped = zipSync(entries);
    return zipped.buffer.slice(zipped.byteOffset, zipped.byteOffset + zipped.byteLength);
}

function utf8(text: string): Uint8Array {
    return new TextEncoder().encode(text);
}

describe('parseKmz', () => {
    it('extracts and parses a doc.kml at the archive root exactly like parseKml would', async () => {
        const kmz = zipOf({ 'doc.kml': utf8(SAMPLE_KML) });

        const route = await parseKmz(kmz);

        expect(route).toEqual(parseKml(SAMPLE_KML));
    });

    it('picks the shallowest .kml entry when the archive has an unconventional layout', async () => {
        const kmz = zipOf({
            'root.kml': utf8(SAMPLE_KML),
            'files/nested/other.kml': utf8('<kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><Point><coordinates>0,0</coordinates></Point></Placemark></Document></kml>'),
        });

        const route = await parseKmz(kmz);

        expect(route.waypoints[0].name).toBe('Start'); // vient de root.kml, pas de files/nested/other.kml
    });

    it('rejects an entry whose declared uncompressed size exceeds the zip-bomb cap', async () => {
        // Un buffer hautement compressible (octet répété) : la taille compressée reste minuscule
        // alors que la taille déclarée non compressée dépasse le plafond — un vrai "zip bomb" à
        // petite échelle, sans avoir besoin d'écrire un fichier de test volumineux sur disque.
        const bomb = new Uint8Array(51 * 1024 * 1024).fill(65);
        const kmz = zipOf({ 'doc.kml': bomb });

        await expect(parseKmz(kmz)).rejects.toThrow();
    });

    it('rejects an entry with a path-traversal name', async () => {
        const kmz = zipOf({ '../../etc/evil.kml': utf8(SAMPLE_KML) });

        await expect(parseKmz(kmz)).rejects.toThrow();
    });

    it('ignores a nested zip/kmz archive and still parses a valid sibling doc.kml', async () => {
        const innerZip = zipSync({ 'inner.kml': utf8(SAMPLE_KML) });
        const kmz = zipOf({
            'doc.kml': utf8(SAMPLE_KML),
            'nested.kmz': innerZip,
        });

        const route = await parseKmz(kmz);

        expect(route.waypoints[0].name).toBe('Start');
    });

    it('throws when the archive has no .kml entry', async () => {
        const kmz = zipOf({ 'readme.txt': utf8('not a KML file') });

        await expect(parseKmz(kmz)).rejects.toThrow();
    });
});
