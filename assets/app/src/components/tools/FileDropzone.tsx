import { cn } from '@/lib/utils';
import { useFileUpload } from './useFileUpload';

interface FileDropzoneProps {
    accept: string;
    multiple?: boolean;
    onFiles: (files: File[]) => void;
    label: string;
}

/** Zone de dépôt accessible (clavier, ARIA) au-dessus de useFileUpload. */
export function FileDropzone({ accept, multiple = false, onFiles, label }: FileDropzoneProps) {
    const { isDragging, dragHandlers, inputRef, inputProps, openFilePicker } = useFileUpload(accept, multiple, onFiles);

    return (
        <div
            role="button"
            tabIndex={0}
            aria-label={label}
            onClick={openFilePicker}
            onKeyDown={(event) => {
                if ('Enter' === event.key || ' ' === event.key) {
                    event.preventDefault();
                    openFilePicker();
                }
            }}
            {...dragHandlers}
            className={cn(
                'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground transition-colors',
                isDragging && 'border-primary bg-accent text-accent-foreground',
            )}
        >
            <p>{label}</p>
            <input ref={inputRef} {...inputProps} />
        </div>
    );
}
