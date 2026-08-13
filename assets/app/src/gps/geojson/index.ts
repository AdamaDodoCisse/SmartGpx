import type { GpsRoute } from '../model';

// TODO(Phase 6) : implémenter le mapping GeoJSON <-> GpsRoute.
// Attention à l'ordre des coordonnées GeoJSON : [longitude, latitude], contrairement au modèle
// interne GpsPoint qui utilise des champs nommés latitude/longitude explicites.

export function parseGeoJson(_content: string): GpsRoute {
    throw new Error('parseGeoJson: not implemented yet (Phase 6).');
}

export function generateGeoJson(_route: GpsRoute): string {
    throw new Error('generateGeoJson: not implemented yet (Phase 6).');
}
