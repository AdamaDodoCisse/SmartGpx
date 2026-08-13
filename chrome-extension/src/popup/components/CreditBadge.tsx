import { DEV_API_ORIGIN, PROD_API_ORIGIN } from '@/lib/env';
import { creditLine, t } from '@/lib/i18n';
import type { AccountPayload } from '@/lib/messages';

export function CreditBadge({ account }: { account: AccountPayload }) {
    const webAppOrigin = import.meta.env.DEV ? DEV_API_ORIGIN : PROD_API_ORIGIN;

    return (
        <div className="credit-badge">
            <span>{creditLine(account.creditBalance, account.hasEverConverted)}</span>
            {0 === account.creditBalance && (
                <a href={`${webAppOrigin}/pricing`} target="_blank" rel="noreferrer">
                    {t('credit.get_more')}
                </a>
            )}
        </div>
    );
}
