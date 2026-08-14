import type { GpsPoint, GpsRoute, GpsTrack, GpsWaypoint } from '../model';

interface NormalizedFeature {
    geometry: { type: string; coordinates: unknown } | null;
    properties: Record<string, unknown> | null;
}

export function parseGeoJson(content: string): GpsRoute {
    const json: unknown = JSON.parse(content);

    // Un export GeoJSON réel est presque toujours une FeatureCollection de Features, jamais une
    // géométrie nue — contrairement à GPX/KML qui n'ont pas cette ambiguïté d'enveloppe. On
    // normalise donc systématiquement vers une liste de features avant de mapper.
    const features = normalizeToFeatures(json);

    const waypoints: GpsWaypoint[] = [];
    const tracks: GpsTrack[] = [];

    for (const { geometry, properties } of features) {
        if (null === geometry) {
            continue;
        }

        const name = 'string' === typeof properties?.name ? properties.name : undefined;
        const description = 'string' === typeof properties?.description ? properties.description : undefined;

        if ('Point' === geometry.type) {
            const point = coordinateToPoint(geometry.coordinates);
            if (null !== point) {
                waypoints.push({ ...point, name, description });
            }
        } else if ('LineString' === geometry.type) {
            tracks.push({ name, points: coordinatesToPoints(geometry.coordinates) });
        } else if ('MultiLineString' === geometry.type && Array.isArray(geometry.coordinates)) {
            // Chaque sous-ligne devient sa propre GpsTrack — le modèle pivot n'a pas de notion de
            // "MultiLineString", et une fusion en une seule trace effacerait silencieusement les
            // coupures entre segments.
            for (const line of geometry.coordinates) {
                tracks.push({ name, points: coordinatesToPoints(line) });
            }
        }
        // Polygon/MultiPoint/MultiPolygon/GeometryCollection : aucun équivalent naturel dans
        // GpsRoute (qui ne modélise que points/traces) — ignorés silencieusement.
    }

    if (0 === waypoints.length && 0 === tracks.length) {
        throw new Error('parseGeoJson : aucun point ou tracé exploitable trouvé dans ce fichier.');
    }

    return { waypoints, tracks, routes: [] };
}

export function generateGeoJson(route: GpsRoute): string {
    const features = [
        ...route.waypoints.map((waypoint) => ({
            type: 'Feature' as const,
            properties: { name: waypoint.name ?? null, description: waypoint.description ?? null },
            geometry: { type: 'Point' as const, coordinates: pointToCoordinate(waypoint) },
        })),
        // Comme generateKml/generateTcx : tracks et routes n'ont pas de distinction dans le
        // modèle GeoJSON (une LineString est une LineString) — fusion documentée et volontaire.
        ...[...route.tracks, ...route.routes].map((track) => ({
            type: 'Feature' as const,
            properties: { name: track.name ?? null },
            geometry: { type: 'LineString' as const, coordinates: track.points.map(pointToCoordinate) },
        })),
    ];

    return JSON.stringify({ type: 'FeatureCollection', features }, null, 2);
}

function normalizeToFeatures(json: unknown): NormalizedFeature[] {
    if (isRecord(json) && 'FeatureCollection' === json.type && Array.isArray(json.features)) {
        return json.features.filter(isRecord).map(toNormalizedFeature);
    }
    if (isRecord(json) && 'Feature' === json.type) {
        return [toNormalizedFeature(json)];
    }
    if (isRecord(json) && 'string' === typeof json.type) {
        // Repli : géométrie nue (non standard pour un export réel, mais acceptée sans coût).
        return [{ geometry: { type: json.type, coordinates: json.coordinates }, properties: null }];
    }

    throw new Error('parseGeoJson : structure non reconnue (ni FeatureCollection, ni Feature, ni géométrie).');
}

function toNormalizedFeature(feature: Record<string, unknown>): NormalizedFeature {
    const geometry = feature.geometry;

    return {
        geometry: isRecord(geometry) && 'string' === typeof geometry.type
            ? { type: geometry.type, coordinates: geometry.coordinates }
            : null,
        properties: isRecord(feature.properties) ? feature.properties : null,
    };
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return 'object' === typeof value && null !== value;
}

function coordinateToPoint(coordinates: unknown): GpsPoint | null {
    // Ordre GeoJSON : [longitude, latitude, altitude?] (RFC 7946 §3.1.1) — même piège que KML
    // (voir gps/kml/index.ts), inverse des champs nommés latitude/longitude de GpsPoint.
    if (!Array.isArray(coordinates) || coordinates.length < 2) {
        return null;
    }

    const [longitude, latitude, elevation] = coordinates as unknown[];
    if ('number' !== typeof latitude || 'number' !== typeof longitude || !Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    return { latitude, longitude, elevation: 'number' === typeof elevation && Number.isFinite(elevation) ? elevation : undefined };
}

function coordinatesToPoints(coordinates: unknown): GpsPoint[] {
    if (!Array.isArray(coordinates)) {
        return [];
    }

    return coordinates.map(coordinateToPoint).filter((point): point is GpsPoint => null !== point);
}

function pointToCoordinate(point: GpsPoint): number[] {
    return undefined !== point.elevation ? [point.longitude, point.latitude, point.elevation] : [point.longitude, point.latitude];
}
