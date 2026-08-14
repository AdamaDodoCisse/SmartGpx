import type { GpsPoint, GpsRoute, GpsTrack, GpsWaypoint } from '../model';
import { childText, directChildren, parseXmlDocument } from '../shared/xml';

const GPX_NS = 'http://www.topografix.com/GPX/1/1';

export function parseGpx(content: string): GpsRoute {
    const doc = parseXmlDocument(content, 'GPX');
    const gpxEl = doc.documentElement;

    const metadataEl = directChildren(gpxEl, 'metadata')[0];
    const name = undefined !== metadataEl ? childText(metadataEl, 'name') : undefined;

    const waypoints = directChildren(gpxEl, 'wpt')
        .map(parseWaypointEl)
        .filter((waypoint): waypoint is GpsWaypoint => null !== waypoint);
    const tracks = directChildren(gpxEl, 'trk').map(parseTrackEl);
    const routes = directChildren(gpxEl, 'rte').map(parseRteEl);

    if (0 === waypoints.length && 0 === tracks.length && 0 === routes.length) {
        throw new Error('parseGpx : aucun point, trace ou itinéraire trouvé — ce fichier ne ressemble pas à un GPX.');
    }

    return { name, waypoints, tracks, routes };
}

export function generateGpx(route: GpsRoute): string {
    const doc = document.implementation.createDocument(GPX_NS, 'gpx', null);
    const gpxEl = doc.documentElement;
    gpxEl.setAttribute('version', '1.1');
    gpxEl.setAttribute('creator', 'SmartGPX');

    if (route.name) {
        const metadataEl = doc.createElementNS(GPX_NS, 'metadata');
        metadataEl.appendChild(textEl(doc, 'name', route.name));
        gpxEl.appendChild(metadataEl);
    }

    for (const waypoint of route.waypoints) {
        gpxEl.appendChild(buildPointEl(doc, 'wpt', waypoint, waypoint.name, waypoint.description));
    }
    for (const track of route.tracks) {
        gpxEl.appendChild(buildTrackEl(doc, track));
    }
    for (const rte of route.routes) {
        gpxEl.appendChild(buildRteEl(doc, rte));
    }

    return `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(doc)}`;
}

function parsePoint(el: Element): GpsPoint | null {
    const latitude = Number.parseFloat(el.getAttribute('lat') ?? '');
    const longitude = Number.parseFloat(el.getAttribute('lon') ?? '');

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    const elevationText = childText(el, 'ele');
    const elevation = undefined !== elevationText ? Number.parseFloat(elevationText) : undefined;

    return {
        latitude,
        longitude,
        elevation: undefined !== elevation && Number.isFinite(elevation) ? elevation : undefined,
        time: childText(el, 'time'),
    };
}

function parseWaypointEl(el: Element): GpsWaypoint | null {
    const point = parsePoint(el);

    return null === point ? null : { ...point, name: childText(el, 'name'), description: childText(el, 'desc') };
}

function parseTrackEl(el: Element): GpsTrack {
    const points = directChildren(el, 'trkseg').flatMap((segment) =>
        directChildren(segment, 'trkpt')
            .map(parsePoint)
            .filter((point): point is GpsPoint => null !== point),
    );

    return { name: childText(el, 'name'), points };
}

function parseRteEl(el: Element): GpsTrack {
    const points = directChildren(el, 'rtept')
        .map(parsePoint)
        .filter((point): point is GpsPoint => null !== point);

    return { name: childText(el, 'name'), points };
}

function textEl(doc: Document, localName: string, text: string): Element {
    const el = doc.createElementNS(GPX_NS, localName);
    el.textContent = text;

    return el;
}

function buildPointEl(doc: Document, tag: string, point: GpsPoint, name?: string, description?: string): Element {
    const el = doc.createElementNS(GPX_NS, tag);
    el.setAttribute('lat', String(point.latitude));
    el.setAttribute('lon', String(point.longitude));

    if (undefined !== point.elevation) {
        el.appendChild(textEl(doc, 'ele', String(point.elevation)));
    }
    if (undefined !== point.time) {
        el.appendChild(textEl(doc, 'time', point.time));
    }
    if (name) {
        el.appendChild(textEl(doc, 'name', name));
    }
    if (description) {
        el.appendChild(textEl(doc, 'desc', description));
    }

    return el;
}

function buildTrackEl(doc: Document, track: GpsTrack): Element {
    const trkEl = doc.createElementNS(GPX_NS, 'trk');

    if (track.name) {
        trkEl.appendChild(textEl(doc, 'name', track.name));
    }

    const trksegEl = doc.createElementNS(GPX_NS, 'trkseg');
    for (const point of track.points) {
        trksegEl.appendChild(buildPointEl(doc, 'trkpt', point));
    }
    trkEl.appendChild(trksegEl);

    return trkEl;
}

function buildRteEl(doc: Document, route: GpsTrack): Element {
    const rteEl = doc.createElementNS(GPX_NS, 'rte');

    if (route.name) {
        rteEl.appendChild(textEl(doc, 'name', route.name));
    }
    for (const point of route.points) {
        rteEl.appendChild(buildPointEl(doc, 'rtept', point));
    }

    return rteEl;
}
