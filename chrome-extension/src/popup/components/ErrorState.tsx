import { CircleAlertIcon } from './icons';

export function ErrorState({ message }: { message: string }) {
    return (
        <p className="error-state">
            <CircleAlertIcon />
            {message}
        </p>
    );
}
