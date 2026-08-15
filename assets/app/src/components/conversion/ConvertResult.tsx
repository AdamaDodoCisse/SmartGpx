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
    routeOptionsApplied: {
        routingPreference: string;
        avoidHighways: boolean;
        avoidTolls: boolean;
        avoidFerries: boolean;
        optimizeWaypointOrder: boolean;
        routeDetail: string;
    };
    costTier: string;
    tollEstimate: { currencyCode: string; amount: number } | null;
    routeLabel: string | null;
    originalStopOrder: string[] | null;
    optimizedStopOrder: string[] | null;
}

interface ConvertResultProps {
    result: ConversionResult;
    onReset: () => void;
}

export function ConvertResult({ result, onReset }: ConvertResultProps) {
    const { t } = useTranslation();

    const distanceKm = (result.distanceMeters / 1000).toFixed(1);
    const durationMinutes = Math.round(result.durationSeconds / 60);
    const { routeOptionsApplied: applied } = result;
    const appliedPreferenceLabels = [
        applied.avoidHighways && t('convert.advanced.avoid.avoidHighways'),
        applied.avoidTolls && t('convert.advanced.avoid.avoidTolls'),
        applied.avoidFerries && t('convert.advanced.avoid.avoidFerries'),
        'TRAFFIC_UNAWARE' !== applied.routingPreference &&
            t(`convert.advanced.routing_preference.${applied.routingPreference.toLowerCase()}`),
    ].filter((label): label is string => 'string' === typeof label);

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
                {result.stops.length > 0 && null === result.optimizedStopOrder && (
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
                {result.tollEstimate && (
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{t('convert.advanced.output.estimated_tolls')}</dt>
                        <dd>
                            {t('convert.advanced.output.toll_estimate', {
                                amount: result.tollEstimate.amount.toFixed(2),
                                currency: result.tollEstimate.currencyCode,
                            })}
                        </dd>
                    </div>
                )}
            </dl>

            {result.optimizedStopOrder && result.originalStopOrder && (
                <div className="mt-4 rounded-md border border-border p-3 text-sm">
                    <p className="font-medium">{t('convert.advanced.stops.optimized_notice')}</p>
                    <div className="mt-2 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <p className="text-muted-foreground">{t('convert.advanced.stops.original')}</p>
                            <ol className="mt-1 list-decimal space-y-0.5 pl-4">
                                {result.originalStopOrder.map((stop) => (
                                    <li key={stop}>{stop}</li>
                                ))}
                            </ol>
                        </div>
                        <div>
                            <p className="text-muted-foreground">{t('convert.advanced.stops.optimized')}</p>
                            <ol className="mt-1 list-decimal space-y-0.5 pl-4">
                                {result.optimizedStopOrder.map((stop) => (
                                    <li key={stop}>{stop}</li>
                                ))}
                            </ol>
                        </div>
                    </div>
                </div>
            )}

            {appliedPreferenceLabels.length > 0 && (
                <div className="mt-4 rounded-md bg-accent px-3 py-2 text-xs text-accent-foreground">
                    <p className="font-medium">{t('convert.advanced.route_selection.preferences_applied')}</p>
                    <p className="mt-1">{appliedPreferenceLabels.join(' · ')}</p>
                </div>
            )}

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
