import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { downloadFile } from '@/lib/downloadFile';
import { generateGpx, parseGpx } from '@/gps/gpx';
import { mergeRoutes, type MergeMode } from '@/gps/merge';
import type { GpsRoute } from '@/gps/model';
import { FileDropzone } from './FileDropzone';
import { ToolPageLayout } from './ToolPageLayout';

interface LoadedFile {
    name: string;
    route: GpsRoute;
}

export function GpxMergeTool() {
    const { t } = useTranslation();
    const [files, setFiles] = useState<LoadedFile[]>([]);
    const [mode, setMode] = useState<MergeMode>('single-track');
    const [error, setError] = useState<string | null>(null);

    function handleFiles(newFiles: File[]): void {
        setError(null);

        Promise.all(
            newFiles.map((file) => file.text().then((content) => ({ name: file.name, route: parseGpx(content) }))),
        )
            .then((loaded) => setFiles((current) => [...current, ...loaded]))
            .catch(() => setError(t('tools.gpx_merge.error')));
    }

    function removeFile(index: number): void {
        setFiles((current) => current.filter((_file, i) => i !== index));
    }

    function handleDownload(): void {
        const merged = mergeRoutes(
            files.map((file) => file.route),
            mode,
        );
        downloadFile(generateGpx(merged), 'merged.gpx', 'application/gpx+xml');
    }

    return (
        <ToolPageLayout>
            <FileDropzone accept=".gpx" multiple onFiles={handleFiles} label={t('tools.drop_file')} />

            {null !== error && (
                <p role="alert" className="mt-3 text-sm text-[var(--error-fg)]">
                    {error}
                </p>
            )}

            {files.length > 0 && (
                <ul className="mt-4 space-y-2">
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${index}`}
                            className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <span>{file.name}</span>
                            <button
                                type="button"
                                onClick={() => removeFile(index)}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                {t('tools.gpx_merge.remove_file')}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {files.length >= 2 && (
                <div className="mt-4">
                    <p className="text-sm font-medium">{t('tools.gpx_merge.mode_label')}</p>
                    <div className="mt-2 flex gap-4 text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                type="radio"
                                name="merge-mode"
                                checked={'single-track' === mode}
                                onChange={() => setMode('single-track')}
                            />
                            {t('tools.gpx_merge.mode_single_track')}
                        </label>
                        <label className="flex items-center gap-2">
                            <input
                                type="radio"
                                name="merge-mode"
                                checked={'separate-segments' === mode}
                                onChange={() => setMode('separate-segments')}
                            />
                            {t('tools.gpx_merge.mode_separate_segments')}
                        </label>
                    </div>
                    <Button className="mt-4" onClick={handleDownload}>
                        {t('tools.download')}
                    </Button>
                </div>
            )}
        </ToolPageLayout>
    );
}
