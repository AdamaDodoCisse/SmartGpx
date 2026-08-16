import { beforeEach, describe, expect, it } from 'vitest';
import { getLandingPage, setLandingPage } from './attribution';

describe('attribution', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('returns undefined when nothing was ever set', () => {
        expect(getLandingPage()).toBeUndefined();
    });

    it('returns the last value set', () => {
        setLandingPage('guide_google_maps_garmin');

        expect(getLandingPage()).toBe('guide_google_maps_garmin');
    });

    it('overwrites a previous landing page rather than keeping the first one', () => {
        setLandingPage('guide_google_maps_garmin');
        setLandingPage('guide_google_maps_wahoo');

        expect(getLandingPage()).toBe('guide_google_maps_wahoo');
    });
});
