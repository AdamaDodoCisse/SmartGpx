import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { parseGpx } from '@/gps/gpx';
import { FileDropzone } from './FileDropzone';
import { ToolPageLayout } from './ToolPageLayout';

type ToolState = { status: 'idle' } | { status: 'error'; message: string } | { status: 'done'; url: string };

/**
 * Ne construit le lien qu'à partir du premier et du dernier point de la trace (origine/
 * destination) — Google Maps recalcule lui-même l'itinéraire entre les deux, l'outil ne cherche
 * pas à rejouer chaque point GPS enregistré comme autant d'étapes (ce que le format d'URL ne
 * supporte de toute façon pas au-delà de quelques dizaines de points).
 */
export function GpxToGoogleMapsTool() {
    const { t } = useTranslation();
    const [state, setState] = useState<ToolState>({ status: 'idle' });
    const [copied, setCopied] = useState(false);

    function handleFiles(files: File[]): void {
        const file = files[0];
        if (undefined === file) {
            return;
        }

        file.text()
            .then((content) => {
                const route = parseGpx(content);
                const points = route.tracks[0]?.points ?? route.routes[0]?.points ?? [];

                if (points.length < 2) {
                    throw new Error('Pas assez de points pour construire un itinéraire.');
                }

                const origin = points[0];
                const destination = points[points.length - 1];
                const url = `https://www.google.com/maps/dir/${origin.latitude},${origin.longitude}/${destination.latitude},${destination.longitude}`;

                setState({ status: 'done', url });
                setCopied(false);
            })
            .catch(() => {
                setState({ status: 'error', message: t('tools.gpx_to_google_maps.error') });
            });
    }

    return (
        <ToolPageLayout>
            <FileDropzone accept=".gpx" onFiles={handleFiles} label={t('tools.drop_file')} />

            {'error' === state.status && (
                <p role="alert" className="mt-3 text-sm text-[var(--error-fg)]">
                    {state.message}
                </p>
            )}

            {'done' === state.status && (
                <div className="mt-4 space-y-3">
                    <a href={state.url} target="_blank" rel="noreferrer" className="block truncate text-sm text-primary underline">
                        {state.url}
                    </a>
                    <Button
                        onClick={() => {
                            void navigator.clipboard.writeText(state.url);
                            setCopied(true);
                        }}
                    >
                        {copied ? t('tools.gpx_to_google_maps.copied') : t('tools.gpx_to_google_maps.copy_link')}
                    </Button>
                </div>
            )}
        </ToolPageLayout>
    );
}
