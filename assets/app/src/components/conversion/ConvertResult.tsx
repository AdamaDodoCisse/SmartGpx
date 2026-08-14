import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { HeroCard } from './HeroCard';

export interface ConversionResult {
    publicId: string;
    origin: string;
    destination: string;
    stops: string[];
    distanceMeters: number;
    durationSeconds: number;
    travelMode: string;
    travelModeInferred: boolean;
    trackPointCount: number;
    downloadUrl: string;
}

interface ConvertResultProps {
    result: ConversionResult;
    onReset: () => void;
}

export function ConvertResult({ result, onReset }: ConvertResultProps) {
    const { t } = useTranslation();

    const distanceKm = (result.distanceMeters / 1000).toFixed(1);
    const durationMinutes = Math.round(result.durationSeconds / 60);

    return (
        <HeroCard>
            <h2 className="text-lg font-semibold">{t('convert.result.title')}</h2>

            <dl className="mt-4 space-y-2 text-sm">
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{t('convert.result.origin')}</dt>
                    <dd className="text-right">{result.origin}</dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{t('convert.result.destination')}</dt>
                    <dd className="text-right">{result.destination}</dd>
                </div>
                {result.stops.length > 0 && (
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{t('convert.result.stops')}</dt>
                        <dd className="text-right">{result.stops.join(', ')}</dd>
                    </div>
                )}
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{t('convert.result.distance')}</dt>
                    <dd>{distanceKm} km</dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{t('convert.result.duration')}</dt>
                    <dd>{durationMinutes} min</dd>
                </div>
                <div className="flex justify-between gap-4">
                    <dt className="text-muted-foreground">{t('convert.result.track_points')}</dt>
                    <dd>{result.trackPointCount}</dd>
                </div>
            </dl>

            {result.travelModeInferred && (
                <p className="mt-4 rounded-md bg-accent px-3 py-2 text-xs text-accent-foreground">
                    {t('convert.result.travel_mode_inferred_notice')}
                </p>
            )}

            <div className="mt-6 flex gap-3">
                <a href={result.downloadUrl}>
                    <Button type="button">{t('convert.result.download')}</Button>
                </a>
                <Button type="button" variant="secondary" onClick={onReset}>
                    {t('convert.result.convert_another')}
                </Button>
            </div>
        </HeroCard>
    );
}
