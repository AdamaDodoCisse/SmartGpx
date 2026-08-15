import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AdvancedRouteOptions } from './AdvancedRouteOptions';
import { ConvertResult, type ConversionResult } from './ConvertResult';
import { HeroCard } from './HeroCard';
import { RouteSelection } from './RouteSelection';
import { DEFAULT_ROUTE_OPTIONS, type ParsedWaypoint, type RouteCandidate, type RouteOptionsState, type RoutingProviderCapabilities } from './routing/types';

interface ConvertHeroProps {
    isAuthenticated: boolean;
    isVerified: boolean;
    csrfToken: string;
    creditBalance: number;
    capabilities: RoutingProviderCapabilities;
}

type ConvertState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'choosing_route'; previewId: string; candidates: RouteCandidate[] }
    | { status: 'success'; result: ConversionResult }
    | { status: 'no_credit' }
    | { status: 'sign_in_required' }
    | { status: 'email_not_verified' }
    | { status: 'error'; message: string };

const HTTP_PAYMENT_REQUIRED = 402;
const HTTP_FORBIDDEN = 403;
const HTTP_GONE = 410;
const PARSE_DEBOUNCE_MS = 500;

/**
 * Itinéraire réel (accepté par GoogleMapsUrlParser, voir GoogleMapsUrlParserTest) utilisé pour
 * pré-remplir le champ via "Try an example route" — jamais soumis automatiquement, l'utilisateur
 * garde la main sur la conversion (et donc sur la consommation de son crédit).
 */
const EXAMPLE_URL = 'https://www.google.com/maps/dir/?api=1&origin=Paris,+France&destination=Lyon,+France&travelmode=driving';

/**
 * @returns le corps JSON commun aux endpoints convert/preview — les champs d'options avancées
 * sont toujours envoyés (le backend filtre lui-même selon les capabilities actives), le preset
 * n'est envoyé que lorsqu'il n'a pas été personnalisé (voir GoogleMapsRouteOptionsMapper).
 */
function buildRequestBody(url: string, options: RouteOptionsState, waypoints: ParsedWaypoint[]): Record<string, unknown> {
    return {
        url,
        travelMode: options.travelMode,
        preset: 'CUSTOM' === options.preset ? null : options.preset,
        avoidHighways: options.avoidHighways,
        avoidTolls: options.avoidTolls,
        avoidFerries: options.avoidFerries,
        routingPreference: options.routingPreference,
        optimizeWaypointOrder: options.optimizeWaypointOrder,
        routeDetail: options.routeDetail,
        showAlternativeRoutes: options.showAlternativeRoutes,
        showFuelEfficientRoute: options.showFuelEfficientRoute,
        showTollEstimates: options.showTollEstimates,
        vehicleEmissionType: options.vehicleEmissionType,
        waypointTypes: waypoints.map((waypoint) => waypoint.type),
    };
}

export function ConvertHero({ isAuthenticated, isVerified, csrfToken, creditBalance, capabilities }: ConvertHeroProps) {
    const { t } = useTranslation();
    const [url, setUrl] = useState('');
    const [options, setOptions] = useState<RouteOptionsState>(DEFAULT_ROUTE_OPTIONS);
    const [waypoints, setWaypoints] = useState<ParsedWaypoint[]>([]);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const [state, setState] = useState<ConvertState>({ status: 'idle' });
    const inputRef = useRef<HTMLInputElement>(null);

    // Peuple la liste des étapes STOP/VIA sans jamais appeler la route de calcul payante — voir
    // ParseGoogleMapsUrlController. Ne se déclenche que lorsque le panneau est ouvert : un
    // utilisateur qui n'ouvre jamais "Advanced options" ne déclenche aucun appel supplémentaire.
    useEffect(() => {
        if (!advancedOpen || '' === url.trim()) {
            setWaypoints([]);

            return;
        }

        const timer = window.setTimeout(() => {
            fetch('/api/conversions/google-maps/parse', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url }),
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((data: unknown) => {
                    if (
                        'object' === typeof data &&
                        null !== data &&
                        'stops' in data &&
                        Array.isArray((data as { stops: unknown }).stops)
                    ) {
                        const stops = (data as { stops: { label: string; index: number }[] }).stops;
                        setWaypoints((previous) =>
                            stops.map((stop) => ({
                                label: stop.label,
                                index: stop.index,
                                type: previous.find((waypoint) => waypoint.index === stop.index)?.type ?? 'STOP',
                            })),
                        );
                    }
                })
                .catch(() => undefined);
        }, PARSE_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [url, advancedOpen]);

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

    if ('choosing_route' === state.status) {
        return (
            <HeroCard>
                <RouteSelection
                    candidates={state.candidates}
                    isSubmitting={false}
                    onBack={() => setState({ status: 'idle' })}
                    onExport={(selectedIndex) => {
                        void exportPreviewedRoute(state.previewId, selectedIndex);
                    }}
                />
            </HeroCard>
        );
    }

    const isLoading = 'loading' === state.status;

    async function exportPreviewedRoute(previewId: string, selectedIndex: number): Promise<void> {
        setState({ status: 'loading' });

        try {
            const response = await fetch('/api/conversions/google-maps/export', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ previewId, selectedIndex }),
            });

            const data: unknown = await response.json();

            if (!response.ok) {
                if (HTTP_PAYMENT_REQUIRED === response.status) {
                    setState({ status: 'no_credit' });

                    return;
                }

                if (HTTP_GONE === response.status) {
                    setState({ status: 'error', message: t('convert.advanced.route_selection.preview_expired') });

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
    }

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

        const wantsRouteChoice = options.showAlternativeRoutes || options.showFuelEfficientRoute;
        const endpoint = wantsRouteChoice ? '/api/conversions/google-maps/preview' : '/api/conversions/google-maps';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(buildRequestBody(url, options, waypoints)),
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

            if (wantsRouteChoice) {
                const preview = data as { previewId: string; candidates: RouteCandidate[] };
                setState({ status: 'choosing_route', previewId: preview.previewId, candidates: preview.candidates });

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
                <Button type="submit" disabled={isLoading}>
                    {isLoading ? t('convert.cta_loading') : t('convert.cta')}
                </Button>
            </form>

            <AdvancedRouteOptions
                capabilities={capabilities}
                options={options}
                onChange={setOptions}
                waypoints={waypoints}
                onWaypointsChange={setWaypoints}
                open={advancedOpen}
                onOpenChange={setAdvancedOpen}
                disabled={isLoading}
            />

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
