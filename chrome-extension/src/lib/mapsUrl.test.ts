import { describe, expect, it } from 'vitest';
import { isGoogleMapsRouteUrl, parseRoutePreview } from './mapsUrl';

describe('isGoogleMapsRouteUrl', () => {
    it('accepts the path-segment directions format', () => {
        expect(isGoogleMapsRouteUrl('https://www.google.com/maps/dir/Cergy/Paris/@49.03,2.07,11z')).toBe(true);
    });

    it('accepts the query-param directions format', () => {
        expect(
            isGoogleMapsRouteUrl('https://www.google.com/maps?api=1&origin=Cergy&destination=Paris'),
        ).toBe(true);
    });

    it('accepts a bare google.fr host', () => {
        expect(isGoogleMapsRouteUrl('https://www.google.fr/maps/dir/Cergy/Paris/')).toBe(true);
    });

    it('accepts short links, deferring resolution to the backend', () => {
        expect(isGoogleMapsRouteUrl('https://maps.app.goo.gl/xyz123')).toBe(true);
        expect(isGoogleMapsRouteUrl('https://goo.gl/maps/xyz123')).toBe(true);
    });

    it('rejects a Google Maps place/search URL', () => {
        expect(isGoogleMapsRouteUrl('https://www.google.com/maps/place/Cergy')).toBe(false);
        expect(isGoogleMapsRouteUrl('https://www.google.com/maps/search/restaurants')).toBe(false);
    });

    it('rejects a non-Maps URL', () => {
        expect(isGoogleMapsRouteUrl('https://www.google.com/search?q=paris')).toBe(false);
        expect(isGoogleMapsRouteUrl('https://example.com/maps/dir/a/b')).toBe(false);
    });

    it('rejects a malformed URL', () => {
        expect(isGoogleMapsRouteUrl('not a url')).toBe(false);
    });
});

describe('parseRoutePreview', () => {
    it('extracts origin and destination from a two-point route', () => {
        expect(parseRoutePreview('https://www.google.com/maps/dir/Cergy/Paris/@49.03,2.07,11z')).toEqual({
            origin: 'Cergy',
            destination: 'Paris',
            stops: [],
        });
    });

    it('extracts intermediate stops', () => {
        expect(parseRoutePreview('https://www.google.com/maps/dir/Cergy/Pontoise/Paris/@49.03,2.07,11z')).toEqual({
            origin: 'Cergy',
            destination: 'Paris',
            stops: ['Pontoise'],
        });
    });

    it('decodes URL-encoded and plus-encoded addresses', () => {
        expect(parseRoutePreview('https://www.google.com/maps/dir/12+Rue+de+Paris/Gare+du+Nord/')).toEqual({
            origin: '12 Rue de Paris',
            destination: 'Gare du Nord',
            stops: [],
        });
    });

    it('ignores the trailing data= segment', () => {
        expect(parseRoutePreview('https://www.google.com/maps/dir/Cergy/Paris/data=!4m2!4m1!3e0')).toEqual({
            origin: 'Cergy',
            destination: 'Paris',
            stops: [],
        });
    });

    it('returns null for the query-param format', () => {
        expect(parseRoutePreview('https://www.google.com/maps?api=1&origin=Cergy&destination=Paris')).toBeNull();
    });

    it('returns null for a short link', () => {
        expect(parseRoutePreview('https://maps.app.goo.gl/xyz123')).toBeNull();
    });

    it('returns null for a single-segment path', () => {
        expect(parseRoutePreview('https://www.google.com/maps/dir/Cergy/')).toBeNull();
    });

    it('returns null for a malformed URL', () => {
        expect(parseRoutePreview('not a url')).toBeNull();
    });
});
