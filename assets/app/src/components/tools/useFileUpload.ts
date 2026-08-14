import { useRef, useState, type ChangeEvent, type DragEvent } from 'react';

export interface UseFileUploadResult {
    isDragging: boolean;
    dragHandlers: {
        onDragOver: (event: DragEvent<HTMLElement>) => void;
        onDragLeave: (event: DragEvent<HTMLElement>) => void;
        onDrop: (event: DragEvent<HTMLElement>) => void;
    };
    inputRef: React.RefObject<HTMLInputElement | null>;
    inputProps: {
        type: 'file';
        accept: string;
        multiple: boolean;
        className: string;
        onChange: (event: ChangeEvent<HTMLInputElement>) => void;
    };
    openFilePicker: () => void;
}

/**
 * Logique pure d'acquisition de fichier (glisser-déposer + sélecteur natif) — ne lit jamais le
 * contenu du fichier elle-même, ce qui la rend indépendante du format (texte pour GPX/KML cette
 * phase, binaire pour KMZ/FIT en Phase 6) et donc réutilisable telle quelle.
 */
export function useFileUpload(accept: string, multiple: boolean, onFiles: (files: File[]) => void): UseFileUploadResult {
    const [isDragging, setIsDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    function handleFileList(fileList: FileList | null): void {
        if (null === fileList || 0 === fileList.length) {
            return;
        }
        onFiles(Array.from(fileList));
    }

    return {
        isDragging,
        dragHandlers: {
            onDragOver: (event) => {
                event.preventDefault();
                setIsDragging(true);
            },
            onDragLeave: () => {
                setIsDragging(false);
            },
            onDrop: (event) => {
                event.preventDefault();
                setIsDragging(false);
                handleFileList(event.dataTransfer.files);
            },
        },
        inputRef,
        inputProps: {
            type: 'file',
            accept,
            multiple,
            className: 'sr-only',
            onChange: (event) => {
                handleFileList(event.target.files);
                // Permet de re-sélectionner le même fichier une seconde fois.
                event.target.value = '';
            },
        },
        openFilePicker: () => inputRef.current?.click(),
    };
}
