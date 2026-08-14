import type { GpsPoint } from '../model';

export interface SimplifyOptions {
    toleranceMeters: number;
    maxPoints?: number;
}

const METERS_PER_DEGREE_LATITUDE = 111_320;

/**
 * Simplifie une trace par Ramer-Douglas-Peucker. La distance perpendiculaire est calculée dans
 * une projection équirectangulaire locale (pas de haversine complète) : suffisamment précise à
 * l'échelle d'une trace GPS unique (une sortie, pas un trajet transcontinental), et bien plus
 * simple qu'une bibliothèque géographique complète.
 *
 * maxPoints est un plafond dur appliqué par décimation uniforme après RDP (jamais une cible que
 * RDP viserait directement) — protège contre une tolérance trop permissive combinée à un plafond
 * strict, sans ré-exécuter RDP avec une tolérance plus élevée (coûteux, résultat imprévisible).
 */
export function simplifyTrack(points: GpsPoint[], options: SimplifyOptions): GpsPoint[] {
    if (points.length <= 2) {
        return points;
    }

    const reference = averageLatLon(points);
    let result = rdp(points, options.toleranceMeters, reference);

    if (undefined !== options.maxPoints && result.length > options.maxPoints) {
        result = capToMaxPoints(result, options.maxPoints);
    }

    return result;
}

interface LocalReference {
    latitude: number;
    longitude: number;
}

function averageLatLon(points: GpsPoint[]): LocalReference {
    const sum = points.reduce(
        (acc, point) => ({ latitude: acc.latitude + point.latitude, longitude: acc.longitude + point.longitude }),
        { latitude: 0, longitude: 0 },
    );

    return { latitude: sum.latitude / points.length, longitude: sum.longitude / points.length };
}

function toLocalMeters(point: GpsPoint, reference: LocalReference): { x: number; y: number } {
    const metersPerDegreeLongitude = METERS_PER_DEGREE_LATITUDE * Math.cos((reference.latitude * Math.PI) / 180);

    return {
        x: (point.longitude - reference.longitude) * metersPerDegreeLongitude,
        y: (point.latitude - reference.latitude) * METERS_PER_DEGREE_LATITUDE,
    };
}

function perpendicularDistanceMeters(point: GpsPoint, start: GpsPoint, end: GpsPoint, reference: LocalReference): number {
    const p = toLocalMeters(point, reference);
    const a = toLocalMeters(start, reference);
    const b = toLocalMeters(end, reference);

    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const lengthSquared = dx * dx + dy * dy;

    if (0 === lengthSquared) {
        return Math.hypot(p.x - a.x, p.y - a.y);
    }

    const numerator = Math.abs(dy * p.x - dx * p.y + b.x * a.y - b.y * a.x);

    return numerator / Math.sqrt(lengthSquared);
}

function rdp(points: GpsPoint[], toleranceMeters: number, reference: LocalReference): GpsPoint[] {
    if (points.length < 3) {
        return points;
    }

    const start = points[0];
    const end = points[points.length - 1];

    let maxDistance = 0;
    let maxIndex = 0;

    for (let i = 1; i < points.length - 1; i += 1) {
        const distance = perpendicularDistanceMeters(points[i], start, end, reference);
        if (distance > maxDistance) {
            maxDistance = distance;
            maxIndex = i;
        }
    }

    if (maxDistance <= toleranceMeters) {
        return [start, end];
    }

    const left = rdp(points.slice(0, maxIndex + 1), toleranceMeters, reference);
    const right = rdp(points.slice(maxIndex), toleranceMeters, reference);

    return [...left.slice(0, -1), ...right];
}

function capToMaxPoints(points: GpsPoint[], maxPoints: number): GpsPoint[] {
    if (maxPoints < 2 || points.length <= maxPoints) {
        return points;
    }

    const step = (points.length - 1) / (maxPoints - 1);
    const result: GpsPoint[] = [];
    for (let i = 0; i < maxPoints; i += 1) {
        result.push(points[Math.round(i * step)]);
    }

    return result;
}
