import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { RouteCandidate } from './routing/types';

interface RouteSelectionProps {
    candidates: RouteCandidate[];
    onExport: (index: number) => void;
    onBack: () => void;
    isSubmitting: boolean;
}

export function RouteSelection({ candidates, onExport, onBack, isSubmitting }: RouteSelectionProps) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState(0);

    return (
        <div>
            <h2 className="text-lg font-semibold">{t('convert.advanced.route_selection.title')}</h2>

            <ul className="mt-4 space-y-2">
                {candidates.map((candidate) => {
                    const isSelected = candidate.index === selected;
                    const distanceKm = (candidate.distanceMeters / 1000).toFixed(1);
                    const durationMinutes = Math.round(candidate.durationSeconds / 60);

                    return (
                        <li key={candidate.index}>
                            <button
                                type="button"
                                onClick={() => setSelected(candidate.index)}
                                disabled={isSubmitting}
                                className={`w-full rounded-md border px-4 py-3 text-left transition-colors ${
                                    isSelected ? 'border-route bg-route/5' : 'border-border hover:border-foreground/30'
                                }`}
                            >
                                <p className="font-mono text-[0.65rem] tracking-[0.15em] text-route">
                                    {candidate.routeLabel && t(`convert.advanced.route_selection.label_${candidate.routeLabel.toLowerCase()}`)}
                                </p>
                                <p className="mt-1 font-medium">
                                    {durationMinutes} min · {distanceKm} km
                                </p>
                                <div className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                    {candidate.avoidsHighways && <span>{t('convert.advanced.route_selection.avoids_highways')}</span>}
                                    {candidate.avoidsTolls && <span>{t('convert.advanced.route_selection.avoids_tolls')}</span>}
                                    {candidate.tollEstimate && (
                                        <span>
                                            {t('convert.advanced.output.toll_estimate', {
                                                amount: candidate.tollEstimate.amount.toFixed(2),
                                                currency: candidate.tollEstimate.currencyCode,
                                            })}
                                        </span>
                                    )}
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ul>

            <div className="mt-5 flex items-center gap-3">
                <Button type="button" onClick={() => onExport(selected)} disabled={isSubmitting}>
                    {isSubmitting ? t('convert.cta_loading') : t('convert.advanced.route_selection.export')}
                </Button>
                <button
                    type="button"
                    onClick={onBack}
                    disabled={isSubmitting}
                    className="text-xs font-medium text-muted-foreground underline decoration-dotted underline-offset-4 hover:text-foreground"
                >
                    {t('convert.back_to_form')}
                </button>
            </div>
        </div>
    );
}
