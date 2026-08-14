import type { GpsPoint, GpsRoute, GpsTrack } from '../model';
import { childText, directChildren, parseXmlDocument } from '../shared/xml';

const TCX_NS = 'http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2';

export function parseTcx(content: string): GpsRoute {
    const doc = parseXmlDocument(content, 'TCX');
    const root = doc.documentElement;

    const activitiesEl = directChildren(root, 'Activities')[0];
    const activityEls = undefined !== activitiesEl ? directChildren(activitiesEl, 'Activity') : [];

    // TCX n'a pas de notion de waypoint isolé ni d'itinéraire (<rte>) — seulement des activités
    // faites de tours (<Lap>) contenant des points chronologiques (<Trackpoint>). Un <Lap> par
    // pause/segment est courant dans les fichiers réels ; on les aplatit tous en une seule
    // GpsTrack par activité, comme gpx/index.ts aplatit déjà les frontières <trkseg> — la
    // frontière entre tours n'a aucune valeur pour un round-trip GPX, aucun outil n'en a besoin.
    const tracks: GpsTrack[] = activityEls.map((activityEl) => {
        const points = directChildren(activityEl, 'Lap').flatMap((lapEl) =>
            directChildren(lapEl, 'Track').flatMap((trackEl) =>
                directChildren(trackEl, 'Trackpoint')
                    .map(parseTrackpointEl)
                    .filter((point): point is GpsPoint => null !== point),
            ),
        );

        return { name: activityEl.getAttribute('Sport') ?? undefined, points };
    });

    if (0 === tracks.length || tracks.every((track) => 0 === track.points.length)) {
        throw new Error('parseTcx : aucune trace trouvée — ce fichier ne ressemble pas à un TCX.');
    }

    return { waypoints: [], tracks, routes: [] };
}

export function generateTcx(route: GpsRoute): string {
    const doc = document.implementation.createDocument(TCX_NS, 'TrainingCenterDatabase', null);
    const activitiesEl = doc.createElementNS(TCX_NS, 'Activities');
    doc.documentElement.appendChild(activitiesEl);

    // TCX ne modélise ni waypoints ni <rte> — comme generateKml fusionne déjà trk/rte sans que ce
    // soit une perte accidentelle, generateTcx ignore silencieusement route.waypoints : TCX est un
    // format "journal d'activité chronologique", pas un format "carte", un point d'intérêt isolé
    // n'a pas d'équivalent naturel. tracks et routes deviennent chacune une <Activity> distincte.
    for (const track of [...route.tracks, ...route.routes]) {
        activitiesEl.appendChild(buildActivityEl(doc, track));
    }

    return `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(doc)}`;
}

function parseTrackpointEl(el: Element): GpsPoint | null {
    const positionEl = directChildren(el, 'Position')[0];
    if (undefined === positionEl) {
        return null; // Trackpoint sans position (capteur cadence/puissance seul) — ignoré.
    }

    const latText = childText(positionEl, 'LatitudeDegrees');
    const lonText = childText(positionEl, 'LongitudeDegrees');
    const latitude = undefined !== latText ? Number.parseFloat(latText) : NaN;
    const longitude = undefined !== lonText ? Number.parseFloat(lonText) : NaN;

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    const elevationText = childText(el, 'AltitudeMeters');

    return {
        latitude,
        longitude,
        elevation: undefined !== elevationText ? Number.parseFloat(elevationText) : undefined,
        time: childText(el, 'Time'),
    };
}

function buildActivityEl(doc: Document, track: GpsTrack): Element {
    const activityEl = doc.createElementNS(TCX_NS, 'Activity');
    activityEl.setAttribute('Sport', track.name ?? 'Other');

    const idEl = doc.createElementNS(TCX_NS, 'Id');
    idEl.textContent = track.points[0]?.time ?? new Date(0).toISOString();
    activityEl.appendChild(idEl);

    const lapEl = doc.createElementNS(TCX_NS, 'Lap');
    lapEl.setAttribute('StartTime', track.points[0]?.time ?? new Date(0).toISOString());
    const trackEl = doc.createElementNS(TCX_NS, 'Track');
    for (const point of track.points) {
        trackEl.appendChild(buildTrackpointEl(doc, point));
    }
    lapEl.appendChild(trackEl);
    activityEl.appendChild(lapEl);

    return activityEl;
}

function buildTrackpointEl(doc: Document, point: GpsPoint): Element {
    const trackpointEl = doc.createElementNS(TCX_NS, 'Trackpoint');
    if (point.time) {
        const timeEl = doc.createElementNS(TCX_NS, 'Time');
        timeEl.textContent = point.time;
        trackpointEl.appendChild(timeEl);
    }

    const positionEl = doc.createElementNS(TCX_NS, 'Position');
    const latEl = doc.createElementNS(TCX_NS, 'LatitudeDegrees');
    latEl.textContent = String(point.latitude);
    const lonEl = doc.createElementNS(TCX_NS, 'LongitudeDegrees');
    lonEl.textContent = String(point.longitude);
    positionEl.append(latEl, lonEl);
    trackpointEl.appendChild(positionEl);

    if (undefined !== point.elevation) {
        const eleEl = doc.createElementNS(TCX_NS, 'AltitudeMeters');
        eleEl.textContent = String(point.elevation);
        trackpointEl.appendChild(eleEl);
    }

    return trackpointEl;
}
