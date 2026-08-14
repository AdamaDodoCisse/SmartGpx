import { useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ConvertResult, type ConversionResult } from './ConvertResult';
import { HeroCard } from './HeroCard';

type TravelMode = 'DRIVE' | 'WALK' | 'BICYCLE' | 'TRANSIT';

interface ConvertHeroProps {
    isAuthenticated: boolean;
    isVerified: boolean;
    csrfToken: string;
    creditBalance: number;
}

type ConvertState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'success'; result: ConversionResult }
    | { status: 'no_credit' }
    | { status: 'sign_in_required' }
    | { status: 'email_not_verified' }
    | { status: 'error'; message: string };

const HTTP_PAYMENT_REQUIRED = 402;
const HTTP_FORBIDDEN = 403;

/**
 * Itinéraire réel (accepté par GoogleMapsUrlParser, voir GoogleMapsUrlParserTest) utilisé pour
 * pré-remplir le champ via "Try an example route" — jamais soumis automatiquement, l'utilisateur
 * garde la main sur la conversion (et donc sur la consommation de son crédit).
 */
const EXAMPLE_URL = 'https://www.google.com/maps/dir/?api=1&origin=Paris,+France&destination=Lyon,+France&travelmode=driving';

export function ConvertHero({ isAuthenticated, isVerified, csrfToken, creditBalance }: ConvertHeroProps) {
    const { t } = useTranslation();
    const [url, setUrl] = useState('');
    const [travelMode, setTravelMode] = useState<TravelMode>('DRIVE');
    const [state, setState] = useState<ConvertState>({ status: 'idle' });
    const inputRef = useRef<HTMLInputElement>(null);

    if ('success' === state.status) {
        return (
            <ConvertResult
                result={state.result}
                onReset={() => {
                    setUrl('');
                    setState({ status: 'idle' });
                }}
            />
        );
    }

    if ('no_credit' === state.status) {
        return (
            <HeroCard>
                <div className="text-center">
                    <p className="font-medium">{t('convert.no_credit.title')}</p>
                    <a
                        href="/pricing"
                        className="mt-4 inline-block rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        {t('convert.no_credit.cta')}
                    </a>
                </div>
            </HeroCard>
        );
    }

    if ('sign_in_required' === state.status) {
        return (
            <HeroCard>
                <div className="text-center">
                    <p className="font-medium">{t('convert.sign_in_required.title')}</p>
                    <div className="mt-4 flex flex-wrap items-center justify-center gap-3">
                        <a
                            href="/register"
                            className="inline-block rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                        >
                            {t('convert.sign_in_required.cta_register')}
                        </a>
                        <a href="/login" className="text-sm font-medium text-muted-foreground hover:text-foreground">
                            {t('convert.sign_in_required.cta_login')}
                        </a>
                    </div>
                    <button
                        type="button"
                        onClick={() => setState({ status: 'idle' })}
                        className="mt-4 text-xs text-muted-foreground underline decoration-dotted underline-offset-4 hover:text-foreground"
                    >
                        {t('convert.back_to_form')}
                    </button>
                </div>
            </HeroCard>
        );
    }

    if ('email_not_verified' === state.status) {
        return (
            <HeroCard>
                <div className="text-center">
                    <p className="font-medium">{t('convert.email_not_verified.title')}</p>
                    <p className="mt-2 text-sm text-muted-foreground">{t('convert.email_not_verified.body')}</p>
                    <button
                        type="button"
                        onClick={() => setState({ status: 'idle' })}
                        className="mt-4 text-xs text-muted-foreground underline decoration-dotted underline-offset-4 hover:text-foreground"
                    >
                        {t('convert.back_to_form')}
                    </button>
                </div>
            </HeroCard>
        );
    }

    const isLoading = 'loading' === state.status;

    const handleSubmit = async (event: FormEvent): Promise<void> => {
        event.preventDefault();

        if (!isAuthenticated) {
            setState({ status: 'sign_in_required' });

            return;
        }

        if (!isVerified) {
            setState({ status: 'email_not_verified' });

            return;
        }

        setState({ status: 'loading' });

        try {
            const response = await fetch('/api/conversions/google-maps', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ url, travelMode }),
            });

            const data: unknown = await response.json();

            if (!response.ok) {
                if (HTTP_PAYMENT_REQUIRED === response.status) {
                    setState({ status: 'no_credit' });

                    return;
                }

                // Défense en profondeur : l'état client (isVerified) peut être obsolète si
                // l'utilisateur a ouvert deux onglets ou attendu longtemps sur la page.
                if (HTTP_FORBIDDEN === response.status) {
                    setState({ status: 'email_not_verified' });

                    return;
                }

                const message =
                    'object' === typeof data && null !== data && 'error' in data && 'string' === typeof data.error
                        ? data.error
                        : t('convert.error.generic');
                setState({ status: 'error', message });

                return;
            }

            setState({ status: 'success', result: data as ConversionResult });
        } catch {
            setState({ status: 'error', message: t('convert.error.generic') });
        }
    };

    return (
        <HeroCard>
            <form onSubmit={(event) => void handleSubmit(event)} className="flex flex-col gap-3 sm:flex-row">
                <label htmlFor="maps-url" className="sr-only">
                    {t('convert.input_label')}
                </label>
                <Input
                    id="maps-url"
                    ref={inputRef}
                    type="text"
                    value={url}
                    onChange={(event) => setUrl(event.target.value)}
                    placeholder={t('convert.input_placeholder')}
                    required
                    disabled={isLoading}
                    className="flex-1 font-mono text-sm"
                />
                <select
                    aria-label={t('convert.travel_mode_label')}
                    value={travelMode}
                    onChange={(event) => setTravelMode(event.target.value as TravelMode)}
                    disabled={isLoading}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                >
                    <option value="DRIVE">{t('convert.travel_mode.drive')}</option>
                    <option value="WALK">{t('convert.travel_mode.walk')}</option>
                    <option value="BICYCLE">{t('convert.travel_mode.bicycle')}</option>
                    <option value="TRANSIT">{t('convert.travel_mode.transit')}</option>
                </select>
                <Button type="submit" disabled={isLoading}>
                    {isLoading ? t('convert.cta_loading') : t('convert.cta')}
                </Button>
            </form>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={() => {
                        setUrl(EXAMPLE_URL);
                        inputRef.current?.focus();
                    }}
                    disabled={isLoading}
                    className="text-xs font-medium text-muted-foreground underline decoration-dotted underline-offset-4 hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                >
                    {t('convert.example_cta')}
                </button>
                <p className="text-xs text-muted-foreground">
                    {isAuthenticated ? t('convert.credit_balance', { count: creditBalance }) : t('convert.free_conversion_notice')}
                </p>
            </div>

            {'error' === state.status && (
                <p role="alert" className="mt-3 text-sm text-[var(--error-fg)]">
                    {state.message}
                </p>
            )}
        </HeroCard>
    );
}
