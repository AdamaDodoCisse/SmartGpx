import { Decoder, Encoder, Profile, Stream } from '@garmin/fitsdk';
import type { ActivityMesg, Encodable, FileIdMesg, LapMesg, RecordMesg, SessionMesg } from '@garmin/fitsdk';
import type { GpsPoint, GpsRoute } from '../model';

// Conversion FIT "semicircles" <-> degrés décimaux : positionLat/positionLong sont des entiers
// 32 bits signés représentant une fraction de 180°, convention native du protocole FIT.
const SEMICIRCLE_TO_DEGREES = 180 / 2 ** 31;
const DEGREES_TO_SEMICIRCLE = 2 ** 31 / 180;

// Constantes des tables d'énumération du protocole FIT. Le SDK type ces champs en `number` brut
// (pas d'union de chaînes malgré les noms symboliques utilisés dans sa documentation), donc on
// utilise directement les valeurs numériques du protocole :
// - file type 4 = "activity"
// - sport 1 = "running"
// - activity type 0 = "manual"
// - manufacturer 255 = "development" (fabricant non enregistré, réservé aux outils tiers)
const FIT_FILE_TYPE_ACTIVITY = 4;
const FIT_SPORT_RUNNING = 1;
const FIT_ACTIVITY_TYPE_MANUAL = 0;
const FIT_MANUFACTURER_DEVELOPMENT = 255;

interface FitRecordMesg {
    positionLat?: unknown;
    positionLong?: unknown;
    altitude?: unknown;
    timestamp?: unknown;
}

export async function parseFit(content: ArrayBuffer): Promise<GpsRoute> {
    const stream = Stream.fromByteArray(new Uint8Array(content));

    if (!Decoder.isFIT(stream)) {
        throw new Error('parseFit : ce fichier ne ressemble pas à un fichier FIT valide.');
    }

    const decoder = new Decoder(stream);
    const { messages, errors } = decoder.read();

    if (errors.length > 0) {
        throw new Error(`parseFit : fichier FIT corrompu ou partiellement illisible (${errors.length} erreur(s)).`);
    }

    const records: FitRecordMesg[] = messages.recordMesgs ?? [];
    const points = records.map(recordToPoint).filter((point): point is GpsPoint => null !== point);

    if (0 === points.length) {
        // Fichier FIT valide mais sans position GPS (ex : entraîneur d'intérieur, capteur
        // puissance/cadence seul) — échec explicite plutôt qu'un GpsRoute vide silencieux.
        throw new Error('parseFit : ce fichier FIT ne contient aucune donnée de position GPS.');
    }

    return { waypoints: [], tracks: [{ points }], routes: [] };
}

function recordToPoint(record: FitRecordMesg): GpsPoint | null {
    const { positionLat, positionLong, altitude, timestamp } = record;

    if ('number' !== typeof positionLat || 'number' !== typeof positionLong) {
        return null; // Ce record n'a pas de position (ex : uniquement fréquence cardiaque).
    }

    return {
        latitude: positionLat * SEMICIRCLE_TO_DEGREES,
        longitude: positionLong * SEMICIRCLE_TO_DEGREES,
        elevation: 'number' === typeof altitude ? altitude : undefined,
        time: timestamp instanceof Date ? timestamp.toISOString() : undefined,
    };
}

/**
 * Exporte uniquement le profil "course à pied" (running) — une exigence produit explicite, pas
 * une limitation technique. Ensemble minimal de messages pour un fichier d'activité valide :
 * FILE_ID + un RECORD par point + un LAP + une SESSION + une ACTIVITY, sans agrégats calculés
 * (distance/calories/fréquence cardiaque) — laissés absents plutôt qu'inventés. Ce choix
 * minimal n'est pas vérifié contre l'import réel d'un appareil/service tiers : à revoir si un
 * échec d'import est un jour signalé (voir documentation/technique/fit.md).
 */
export async function generateFit(route: GpsRoute): Promise<ArrayBuffer> {
    const points = route.tracks[0]?.points ?? route.routes[0]?.points ?? [];

    if (0 === points.length) {
        throw new Error('generateFit : aucune trace ou itinéraire à exporter.');
    }

    const now = new Date();
    const firstPointTime = points[0]?.time;
    const lastPointTime = points[points.length - 1]?.time;
    const startTime = firstPointTime ? new Date(firstPointTime) : now;
    const endTime = lastPointTime ? new Date(lastPointTime) : now;
    const elapsedSeconds = Math.max(0, (endTime.getTime() - startTime.getTime()) / 1000);

    const encoder = new Encoder();

    // encoder.writeMesg attend Encodable<Mesg> (mesgNum + rien d'autre au niveau du type de base) :
    // typer chaque littéral via Encodable<XxxMesg> plutôt que de le passer inline évite un rejet
    // par la vérification des propriétés en excès de TypeScript sur un littéral d'objet — le
    // même message, une fois porté par une variable nommée, reste structurellement assignable.
    const fileIdMesg: Encodable<FileIdMesg> = {
        mesgNum: Profile.MesgNum.FILE_ID,
        type: FIT_FILE_TYPE_ACTIVITY,
        manufacturer: FIT_MANUFACTURER_DEVELOPMENT,
        product: 0,
        timeCreated: now,
    };
    encoder.writeMesg(fileIdMesg);

    for (const point of points) {
        const recordMesg: Encodable<RecordMesg> = {
            mesgNum: Profile.MesgNum.RECORD,
            timestamp: point.time ? new Date(point.time) : now,
            positionLat: Math.round(point.latitude * DEGREES_TO_SEMICIRCLE),
            positionLong: Math.round(point.longitude * DEGREES_TO_SEMICIRCLE),
            ...(undefined !== point.elevation ? { altitude: point.elevation } : {}),
        };
        encoder.writeMesg(recordMesg);
    }

    const lapMesg: Encodable<LapMesg> = {
        mesgNum: Profile.MesgNum.LAP,
        timestamp: endTime,
        startTime,
        totalElapsedTime: elapsedSeconds,
        totalTimerTime: elapsedSeconds,
        sport: FIT_SPORT_RUNNING,
    };
    encoder.writeMesg(lapMesg);

    const sessionMesg: Encodable<SessionMesg> = {
        mesgNum: Profile.MesgNum.SESSION,
        timestamp: endTime,
        startTime,
        totalElapsedTime: elapsedSeconds,
        totalTimerTime: elapsedSeconds,
        sport: FIT_SPORT_RUNNING,
        firstLapIndex: 0,
        numLaps: 1,
    };
    encoder.writeMesg(sessionMesg);

    const activityMesg: Encodable<ActivityMesg> = {
        mesgNum: Profile.MesgNum.ACTIVITY,
        timestamp: endTime,
        totalTimerTime: elapsedSeconds,
        numSessions: 1,
        type: FIT_ACTIVITY_TYPE_MANUAL,
    };
    encoder.writeMesg(activityMesg);

    const bytes = encoder.close();
    const buffer = new ArrayBuffer(bytes.byteLength);
    new Uint8Array(buffer).set(bytes);

    return buffer;
}
