import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { downloadFile } from '@/lib/downloadFile';
import type { GpsRoute } from '@/gps/model';
import { FileDropzone } from './FileDropzone';
import { ToolPageLayout } from './ToolPageLayout';

/**
 * Étroitit le contenu lu en `string` pour les convertisseurs texte — évite un cast `as string`
 * dans chaque point de montage (readAs="text" garantit le type à l'exécution, cette fonction le
 * fait échouer bruyamment plutôt que silencieusement si jamais ce n'était pas le cas).
 */
export function asText(content: string | ArrayBuffer): string {
    if ('string' !== typeof content) {
        throw new Error('asText : contenu binaire inattendu pour un convertisseur texte.');
    }

    return content;
}

/** Même rôle que asText, pour les convertisseurs binaires (readAs="arrayBuffer"). */
export function asArrayBuffer(content: string | ArrayBuffer): ArrayBuffer {
    if ('string' === typeof content) {
        throw new Error('asArrayBuffer : contenu texte inattendu pour un convertisseur binaire.');
    }

    return content;
}

export interface SingleFileConverterToolProps {
    accept: string;
    readAs: 'text' | 'arrayBuffer';
    parse: (content: string | ArrayBuffer) => GpsRoute | Promise<GpsRoute>;
    generate: (route: GpsRoute) => string | ArrayBuffer | Promise<string | ArrayBuffer>;
    outputFileName: (originalName: string) => string;
    outputMimeType: string;
    i18nPrefix: string;
}

type ToolState =
    | { status: 'idle' }
    | { status: 'processing' }
    | { status: 'error'; message: string }
    | { status: 'done'; fileName: string; content: string | ArrayBuffer };

/**
 * Composant générique upload → parse → generate → téléchargement, utilisé par KML → GPX et
 * GPX → KML aujourd'hui, et par les convertisseurs de la Phase 6 sans modification.
 */
export function SingleFileConverterTool({
    accept,
    readAs,
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

        const readPromise: Promise<string | ArrayBuffer> = 'text' === readAs ? file.text() : file.arrayBuffer();

        readPromise
            .then((content) => Promise.resolve(parse(content)))
            .then((route) => Promise.resolve(generate(route)))
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
                <p role="alert" className="mt-3 text-sm text-[var(--error-fg)]">
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
