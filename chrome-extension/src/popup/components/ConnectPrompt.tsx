import { DEV_API_ORIGIN, PROD_API_ORIGIN } from '@/lib/env';
import { t } from '@/lib/i18n';

export function ConnectPrompt() {
    const webAppOrigin = import.meta.env.DEV ? DEV_API_ORIGIN : PROD_API_ORIGIN;

    function handleConnect() {
        chrome.tabs.create({ url: `${webAppOrigin}/account/extensions` });
    }

    return (
        <div className="state-block">
            <p>{t('connect.prompt')}</p>
            <button type="button" className="primary-button" onClick={handleConnect}>
                {t('connect.cta')}
            </button>
        </div>
    );
}
