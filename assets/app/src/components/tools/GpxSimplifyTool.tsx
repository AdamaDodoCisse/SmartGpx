import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { downloadFile } from '@/lib/downloadFile';
import { generateGpx, parseGpx } from '@/gps/gpx';
import type { GpsPoint, GpsRoute } from '@/gps/model';
import { simplifyTrack } from '@/gps/simplify';
import { FileDropzone } from './FileDropzone';
import { StatsBadge } from './StatsBadge';
import { ToolPageLayout } from './ToolPageLayout';

type ToolState =
    | { status: 'idle' }
    | { status: 'error'; message: string }
    | { status: 'ready'; route: GpsRoute; points: GpsPoint[]; fileName: string };

const DEFAULT_TOLERANCE_METERS = 10;
const MAX_TOLERANCE_METERS = 100;

export function GpxSimplifyTool() {
    const { t } = useTranslation();
    const [state, setState] = useState<ToolState>({ status: 'idle' });
    const [toleranceMeters, setToleranceMeters] = useState(DEFAULT_TOLERANCE_METERS);

    function handleFiles(files: File[]): void {
        const file = files[0];
        if (undefined === file) {
            return;
        }

        file.text()
            .then((content) => {
                const route = parseGpx(content);
                const points = route.tracks[0]?.points ?? route.routes[0]?.points;

                if (undefined === points || points.length === 0) {
                    throw new Error('Aucune trace ou itinéraire à simplifier dans ce fichier.');
                }

                setState({ status: 'ready', route, points, fileName: file.name });
            })
            .catch(() => {
                setState({ status: 'error', message: t('tools.gpx_simplify.error') });
            });
    }

    if ('ready' !== state.status) {
        return (
            <ToolPageLayout>
                <FileDropzone accept=".gpx" onFiles={handleFiles} label={t('tools.drop_file')} />
                {'error' === state.status && (
                    <p role="alert" className="mt-3 text-sm text-red-600">
                        {state.message}
                    </p>
                )}
            </ToolPageLayout>
        );
    }

    const simplified = simplifyTrack(state.points, { toleranceMeters });
    const simplifiedRoute: GpsRoute = {
        name: state.route.name,
        waypoints: state.route.waypoints,
        tracks: [{ name: state.route.tracks[0]?.name, points: simplified }],
        routes: [],
    };
    const outputFileName = `${state.fileName.replace(/\.gpx$/i, '')}-simplified.gpx`;

    return (
        <ToolPageLayout>
            <div className="rounded-md border border-border p-4">
                <label htmlFor="tolerance" className="block text-sm font-medium">
                    {t('tools.gpx_simplify.tolerance_label')}
                </label>
                <input
                    id="tolerance"
                    type="range"
                    min={1}
                    max={MAX_TOLERANCE_METERS}
                    value={toleranceMeters}
                    onChange={(event) => setToleranceMeters(Number(event.target.value))}
                    className="mt-2 w-full"
                />
                <p className="mt-1 text-xs text-muted-foreground">{toleranceMeters} m</p>

                <StatsBadge before={state.points.length} after={simplified.length} />
                <p className="mt-1 text-xs text-muted-foreground">{t('tools.gpx_simplify.honesty_notice')}</p>

                <Button
                    className="mt-4"
                    onClick={() => downloadFile(generateGpx(simplifiedRoute), outputFileName, 'application/gpx+xml')}
                >
                    {t('tools.download')}
                </Button>
            </div>
        </ToolPageLayout>
    );
}
