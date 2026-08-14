import type { GpsPoint, GpsRoute, GpsTrack, GpsWaypoint } from '../model';
import { childText, directChildren, parseXmlDocument } from '../shared/xml';

const KML_NS = 'http://www.earth.google.com/kml/2.2';

export function parseKml(content: string): GpsRoute {
    const doc = parseXmlDocument(content, 'KML');
    const placemarks = Array.from(doc.getElementsByTagName('Placemark'));

    const waypoints: GpsWaypoint[] = [];
    const tracks: GpsTrack[] = [];

    for (const placemark of placemarks) {
        const name = childText(placemark, 'name');
        const description = childText(placemark, 'description');
        const pointEl = directChildren(placemark, 'Point')[0];
        const lineStringEl = directChildren(placemark, 'LineString')[0];

        if (undefined !== pointEl) {
            const coordinatesText = childText(pointEl, 'coordinates');
            const point = undefined !== coordinatesText ? parseCoordinate(coordinatesText) : null;
            if (null !== point) {
                waypoints.push({ ...point, name, description });
            }
        } else if (undefined !== lineStringEl) {
            const coordinatesText = childText(lineStringEl, 'coordinates');
            tracks.push({ name, points: undefined !== coordinatesText ? parseCoordinateList(coordinatesText) : [] });
        }
    }

    if (0 === waypoints.length && 0 === tracks.length) {
        throw new Error('parseKml : aucun point ou tracé trouvé — ce fichier ne ressemble pas à un KML.');
    }

    return { waypoints, tracks, routes: [] };
}

export function generateKml(route: GpsRoute): string {
    const doc = document.implementation.createDocument(KML_NS, 'kml', null);
    const documentEl = doc.createElementNS(KML_NS, 'Document');
    doc.documentElement.appendChild(documentEl);

    for (const waypoint of route.waypoints) {
        documentEl.appendChild(buildPointPlacemark(doc, waypoint));
    }
    // GPX distingue <trk> et <rte> ; KML n'a qu'une seule notion de tracé (LineString) — fusion
    // volontaire et documentée, pas une perte accidentelle (voir documentation/technique/kml-kmz.md).
    for (const track of [...route.tracks, ...route.routes]) {
        documentEl.appendChild(buildLineStringPlacemark(doc, track));
    }

    return `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(doc)}`;
}

function parseCoordinate(text: string): GpsPoint | null {
    // L'ordre des coordonnées KML est lon,lat[,ele] — inverse des champs nommés de GpsPoint,
    // même piège que GeoJSON (voir App\Routing\Provider\GoogleRoutesProvider côté backend).
    const [longitude, latitude, elevation] = text.trim().split(',').map(Number.parseFloat);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    return { latitude, longitude, elevation: Number.isFinite(elevation) ? elevation : undefined };
}

function parseCoordinateList(text: string): GpsPoint[] {
    return text
        .trim()
        .split(/\s+/)
        .map(parseCoordinate)
        .filter((point): point is GpsPoint => null !== point);
}

function coordinateText(point: GpsPoint): string {
    return undefined !== point.elevation
        ? `${point.longitude},${point.latitude},${point.elevation}`
        : `${point.longitude},${point.latitude}`;
}

function buildPointPlacemark(doc: Document, waypoint: GpsWaypoint): Element {
    const placemarkEl = doc.createElementNS(KML_NS, 'Placemark');
    if (waypoint.name) {
        placemarkEl.appendChild(textEl(doc, 'name', waypoint.name));
    }
    if (waypoint.description) {
        placemarkEl.appendChild(textEl(doc, 'description', waypoint.description));
    }

    const pointEl = doc.createElementNS(KML_NS, 'Point');
    pointEl.appendChild(textEl(doc, 'coordinates', coordinateText(waypoint)));
    placemarkEl.appendChild(pointEl);

    return placemarkEl;
}

function buildLineStringPlacemark(doc: Document, track: GpsTrack): Element {
    const placemarkEl = doc.createElementNS(KML_NS, 'Placemark');
    if (track.name) {
        placemarkEl.appendChild(textEl(doc, 'name', track.name));
    }

    const lineStringEl = doc.createElementNS(KML_NS, 'LineString');
    lineStringEl.appendChild(textEl(doc, 'coordinates', track.points.map(coordinateText).join(' ')));
    placemarkEl.appendChild(lineStringEl);

    return placemarkEl;
}

function textEl(doc: Document, localName: string, text: string): Element {
    const el = doc.createElementNS(KML_NS, localName);
    el.textContent = text;

    return el;
}
