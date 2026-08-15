import { pushToDataLayer } from '@/lib/dataLayer';
import { buildPurchaseEvent, decidePollOutcome, type ConfirmAnalyticsResponse } from '@/billing/checkoutSuccessPolling';

/**
 * Pas une île React (voir CLAUDE.md) : cette page ne fait qu'interroger un endpoint et basculer
 * entre trois blocs pré-traduits par Twig — même principe que le bouton de thème, du vanilla TS
 * suffit, pas de framework ni de runtime i18n à charger pour une page vue une seule fois par
 * achat. La logique de décision testable vit dans @/billing/checkoutSuccessPolling.
 */
const MAX_ATTEMPTS = 8;
const POLL_INTERVAL_MS = 2000;

function showState(root: HTMLElement, state: string): void {
    root.querySelectorAll<HTMLElement>('[data-state]').forEach((element) => {
        element.hidden = element.dataset.state !== state;
    });
}

const root = document.getElementById('billing-checkout-success-root');

if (root instanceof HTMLElement) {
    const confirmUrl = root.dataset.confirmUrl ?? '';
    const csrfToken = root.dataset.csrfToken ?? '';

    const poll = (attempt: number): void => {
        fetch(confirmUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: '{}',
        })
            .then((response) => (response.ok ? (response.json() as Promise<ConfirmAnalyticsResponse>) : Promise.reject(new Error('confirm-analytics request failed'))))
            .then((data) => {
                const decision = decidePollOutcome(data, attempt, MAX_ATTEMPTS);

                if ('paid' === decision.type) {
                    if (decision.push) {
                        pushToDataLayer(buildPurchaseEvent(decision.analytics));
                    }
                    showState(root, 'paid');

                    return;
                }

                if ('unconfirmed' === decision.type) {
                    showState(root, 'unconfirmed');

                    return;
                }

                window.setTimeout(() => poll(attempt + 1), POLL_INTERVAL_MS);
            })
            .catch(() => {
                if (attempt >= MAX_ATTEMPTS) {
                    showState(root, 'unconfirmed');

                    return;
                }

                window.setTimeout(() => poll(attempt + 1), POLL_INTERVAL_MS);
            });
    };

    poll(1);
}
