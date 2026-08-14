import { DownloadIcon } from './icons';

export function ExportButton({
    label,
    disabled,
    onClick,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <button type="button" className="primary-button" disabled={disabled} onClick={onClick}>
            <DownloadIcon />
            {label}
        </button>
    );
}
