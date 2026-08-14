import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface ExtensionConnectProps {
    token: string;
    extensionId: string;
    apiOrigin: string;
}

type ConnectState = 'connecting' | 'success' | 'failure';

function isReceivedResponse(response: unknown): response is { received: true } {
    return (
        'object' === typeof response
        && null !== response
        && 'received' in response
        && true === (response as { received: unknown }).received
    );
}

export function ExtensionConnect({ token, extensionId, apiOrigin }: ExtensionConnectProps) {
    const { t } = useTranslation();
    const [state, setState] = useState<ConnectState>('connecting');

    useEffect(() => {
        if ('' === extensionId || 'undefined' === typeof chrome || !chrome.runtime) {
            setState('failure');

            return;
        }

        chrome.runtime.sendMessage(
            extensionId,
            { type: 'SMARTGPX_CONNECT', token, apiOrigin },
            (response) => {
                if (chrome?.runtime?.lastError || !isReceivedResponse(response)) {
                    setState('failure');

                    return;
                }

                setState('success');
            },
        );
    }, [token, extensionId, apiOrigin]);

    if ('success' === state) {
        return <p className="text-sm text-muted-foreground">{t('extension.connect.success')}</p>;
    }

    if ('failure' === state) {
        return (
            <p role="alert" className="text-sm text-[var(--error-fg)]">
                {t('extension.connect.failure')}
            </p>
        );
    }

    return <p className="text-sm text-muted-foreground">{t('extension.connect.instructions')}</p>;
}
