/**
 * Modèle interne partagé par tous les modules gps/*. Chaque format (GPX, KML, TCX, FIT,
 * GeoJSON, ...) convertit depuis/vers cette forme commune plutôt que de convertir directement
 * d'un format vers un autre — un seul mapping par format à maintenir, pas une matrice complète.
 */

export interface GpsPoint {
    latitude: number;
    longitude: number;
    elevation?: number;
    time?: string;
}

export interface GpsWaypoint extends GpsPoint {
    name?: string;
    description?: string;
}

export interface GpsTrack {
    name?: string;
    points: GpsPoint[];
}

export interface GpsRoute {
    name?: string;
    waypoints: GpsWaypoint[];
    tracks: GpsTrack[];
    /** GPX <rte> — distinct de <trk> mais structurellement identique (nom + liste de points). */
    routes: GpsTrack[];
}
