import 'leaflet/dist/leaflet.css';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CircleMarker, MapContainer, Polyline, TileLayer, Tooltip, useMap } from 'react-leaflet';
import type { LatLngTuple } from 'leaflet';
import { parseGpx } from '@/gps/gpx';
import type { GpsRoute, GpsTrack } from '@/gps/model';
import { FileDropzone } from './FileDropzone';
import { ToolPageLayout } from './ToolPageLayout';

type ToolState = { status: 'idle' } | { status: 'error'; message: string } | { status: 'done'; route: GpsRoute };

// CircleMarker plutôt que Marker : évite le problème classique des icônes par défaut de Leaflet
// dont les chemins d'image ne se résolvent pas correctement une fois passés dans un bundler.
function toLatLng(point: { latitude: number; longitude: number }): LatLngTuple {
    return [point.latitude, point.longitude];
}

function FitBounds({ positions }: { positions: LatLngTuple[] }) {
    const map = useMap();

    useEffect(() => {
        // Leaflet mesure son conteneur au montage ; comme cette carte n'existe que dans l'état
        // "done" (montée fraîche après upload, dans une hauteur en vh), cette mesure arrive
        // parfois avant que la mise en page ne soit stabilisée, et les tuiles se dessinent pour
        // une taille obsolète. invalidateSize() doit s'exécuter — et se terminer — avant
        // fitBounds(), sans quoi fitBounds calcule sa vue contre la bonne taille pendant
        // qu'invalidateSize (déclenché après coup) recentre la carte par-dessus.
        const frame = requestAnimationFrame(() => {
            map.invalidateSize(false);

            if (positions.length > 0) {
                map.fitBounds(positions, { animate: false });
            }
        });

        return () => cancelAnimationFrame(frame);
    }, [positions, map]);

    return null;
}

export function GpxViewerTool() {
    const { t } = useTranslation();
    const [state, setState] = useState<ToolState>({ status: 'idle' });

    function handleFiles(files: File[]): void {
        const file = files[0];
        if (undefined === file) {
            return;
        }

        file.text()
            .then((content) => setState({ status: 'done', route: parseGpx(content) }))
            .catch(() => setState({ status: 'error', message: t('tools.gpx_viewer.error') }));
    }

    if ('done' !== state.status) {
        return (
            <ToolPageLayout>
                <FileDropzone accept=".gpx" onFiles={handleFiles} label={t('tools.drop_file')} />
                {'error' === state.status && (
                    <p role="alert" className="mt-3 text-sm text-[var(--error-fg)]">
                        {state.message}
                    </p>
                )}
            </ToolPageLayout>
        );
    }

    const lines: GpsTrack[] = [...state.route.tracks, ...state.route.routes];
    const positions = lines.flatMap((line) => line.points.map(toLatLng));
    const center: LatLngTuple = positions[0] ?? [0, 0];

    return (
        // max-w-xl convient à un formulaire de dépôt de fichier, pas à une carte : une fois la
        // trace chargée, l'outil sort de la largeur étroite partagée par ToolPageLayout pour
        // donner à la carte la place qu'elle mérite sur desktop.
        <div className="mx-auto mt-6 max-w-4xl">
            <div className="h-[70vh] max-h-[600px] min-h-[360px] w-full overflow-hidden rounded-md border border-border">
                <MapContainer center={center} zoom={13} className="h-full w-full">
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <FitBounds positions={positions} />
                    {lines.map((line, index) => (
                        <Polyline key={index} positions={line.points.map(toLatLng)} pathOptions={{ color: 'var(--route)' }} />
                    ))}
                    {state.route.waypoints.map((waypoint, index) => (
                        <CircleMarker key={index} center={toLatLng(waypoint)} radius={6} pathOptions={{ color: 'var(--primary)' }}>
                            {waypoint.name && <Tooltip>{waypoint.name}</Tooltip>}
                        </CircleMarker>
                    ))}
                </MapContainer>
            </div>
        </div>
    );
}
