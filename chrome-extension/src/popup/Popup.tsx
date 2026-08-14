import { useEffect, useMemo, useState } from 'react';
import { t } from '@/lib/i18n';
import { isGoogleMapsRouteUrl, parseRoutePreview } from '@/lib/mapsUrl';
import { sendExtensionRequest } from '@/lib/messages';
import type { AccountPayload, ConversionPayload } from '@/lib/messages';
import { ConnectPrompt } from './components/ConnectPrompt';
import { CreditBadge } from './components/CreditBadge';
import { ErrorState } from './components/ErrorState';
import { ExportButton } from './components/ExportButton';
import { CircleCheckIcon, LogoMark } from './components/icons';
import { RouteSummary } from './components/RouteSummary';

type Status = 'loading' | 'not-connected' | 'ready' | 'error';
type ExportPhase = 'idle' | 'converting' | 'success';

export function Popup() {
    const [status, setStatus] = useState<Status>('loading');
    const [account, setAccount] = useState<AccountPayload | null>(null);
    const [tabUrl, setTabUrl] = useState<string | null>(null);
    const [phase, setPhase] = useState<ExportPhase>('idle');
    const [lastConversion, setLastConversion] = useState<ConversionPayload | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
            if (cancelled) {
                return;
            }
            setTabUrl(tab?.url ?? null);

            const response = await sendExtensionRequest<AccountPayload>({ type: 'GET_ACCOUNT' });
            if (cancelled) {
                return;
            }

            if (!response.ok) {
                setStatus(response.requiresReconnect ? 'not-connected' : 'error');
                if (!response.requiresReconnect) {
                    setErrorMessage(response.error);
                }
                return;
            }

            setAccount(response.data);
            setStatus('ready');
        }

        load().catch(() => {
            if (!cancelled) {
                setErrorMessage(t('error.generic'));
                setStatus('error');
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    const onDirectionsPage = useMemo(() => null !== tabUrl && isGoogleMapsRouteUrl(tabUrl), [tabUrl]);
    const preview = useMemo(() => (null === tabUrl ? null : parseRoutePreview(tabUrl)), [tabUrl]);

    async function handleExport() {
        if (null === tabUrl) {
            return;
        }

        setPhase('converting');
        setErrorMessage(null);

        const conversionResponse = await sendExtensionRequest<ConversionPayload>({
            type: 'CONVERT',
            googleMapsUrl: tabUrl,
        });

        if (!conversionResponse.ok) {
            setPhase('idle');
            if (conversionResponse.requiresReconnect) {
                setStatus('not-connected');
            } else {
                setErrorMessage(conversionResponse.error);
            }
            return;
        }

        const conversion = conversionResponse.data;
        const downloadResponse = await sendExtensionRequest<{ downloadId: number }>({
            type: 'DOWNLOAD',
            downloadUrl: conversion.downloadUrl,
            suggestedFileName: `smartgpx-${conversion.publicId}.gpx`,
        });

        if (!downloadResponse.ok) {
            setPhase('idle');
            setErrorMessage(downloadResponse.error);
            return;
        }

        setLastConversion(conversion);
        setPhase('success');

        const refreshed = await sendExtensionRequest<AccountPayload>({ type: 'GET_ACCOUNT' });
        if (refreshed.ok) {
            setAccount(refreshed.data);
        }
    }

    function handleExportAnother() {
        setPhase('idle');
        setLastConversion(null);
    }

    return (
        <div className="popup">
            <header className="popup-header">
                <LogoMark width={22} height={22} className="popup-header-mark" />
                <span className="popup-header-title">SmartGPX</span>
            </header>

            {'loading' === status && <p className="muted-text">{t('loading')}</p>}

            {'not-connected' === status && <ConnectPrompt />}

            {'error' === status && <ErrorState message={errorMessage ?? t('error.generic')} />}

            {'ready' === status && null !== account && (
                <div className="state-block">
                    {!onDirectionsPage && <p className="muted-text">{t('not_on_maps')}</p>}

                    {onDirectionsPage && (
                        <>
                            {null !== preview && <RouteSummary preview={preview} />}

                            {'success' === phase && null !== lastConversion ? (
                                <>
                                    <p className="success-state">
                                        <CircleCheckIcon />
                                        {t('export.success')}
                                    </p>
                                    <ExportButton label={t('export.another')} disabled={false} onClick={handleExportAnother} />
                                </>
                            ) : (
                                <ExportButton
                                    label={'converting' === phase ? t('export.loading') : t('export.cta')}
                                    disabled={'converting' === phase}
                                    onClick={handleExport}
                                />
                            )}

                            {null !== errorMessage && 'converting' !== phase && <ErrorState message={errorMessage} />}
                        </>
                    )}

                    <CreditBadge account={account} />
                </div>
            )}
        </div>
    );
}
