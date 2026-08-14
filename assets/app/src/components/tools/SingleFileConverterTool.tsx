import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { downloadFile } from '@/lib/downloadFile';
import type { GpsRoute } from '@/gps/model';
import { FileDropzone } from './FileDropzone';
import { ToolPageLayout } from './ToolPageLayout';

export interface SingleFileConverterToolProps {
    accept: string;
    parse: (content: string) => GpsRoute;
    generate: (route: GpsRoute) => string;
    outputFileName: (originalName: string) => string;
    outputMimeType: string;
    i18nPrefix: string;
}

type ToolState =
    | { status: 'idle' }
    | { status: 'processing' }
    | { status: 'error'; message: string }
    | { status: 'done'; fileName: string; content: string };

/**
 * Composant générique upload → parse → generate → téléchargement, utilisé par KML → GPX et
 * GPX → KML aujourd'hui, et par les convertisseurs de la Phase 6 sans modification.
 */
export function SingleFileConverterTool({
    accept,
    parse,
    generate,
    outputFileName,
    outputMimeType,
    i18nPrefix,
}: SingleFileConverterToolProps) {
    const { t } = useTranslation();
    const [state, setState] = useState<ToolState>({ status: 'idle' });

    function handleFiles(files: File[]): void {
        const file = files[0];
        if (undefined === file) {
            return;
        }

        setState({ status: 'processing' });

        file.text()
            .then((content) => generate(parse(content)))
            .then((generated) => {
                setState({ status: 'done', fileName: outputFileName(file.name), content: generated });
            })
            .catch(() => {
                setState({ status: 'error', message: t(`${i18nPrefix}.error`) });
            });
    }

    return (
        <ToolPageLayout>
            <FileDropzone accept={accept} onFiles={handleFiles} label={t('tools.drop_file')} />

            {'error' === state.status && (
                <p role="alert" className="mt-3 text-sm text-red-600">
                    {state.message}
                </p>
            )}

            {'done' === state.status && (
                <div className="mt-4 flex items-center justify-between rounded-md border border-border px-4 py-3">
                    <span className="text-sm">{state.fileName}</span>
                    <Button onClick={() => downloadFile(state.content, state.fileName, outputMimeType)}>
                        {t('tools.download')}
                    </Button>
                </div>
            )}
        </ToolPageLayout>
    );
}
