export interface ConfirmAnalyticsAnalytics {
    transactionId: string;
    value: number;
    currency: string;
    credits: number;
    itemId: string;
    itemName: string;
}

export interface ConfirmAnalyticsResponse {
    status: 'pending' | 'completed' | 'failed';
    claimed: boolean;
    analytics: ConfirmAnalyticsAnalytics | null;
}

export type PollDecision =
    | { type: 'paid'; push: boolean; analytics: ConfirmAnalyticsAnalytics }
    | { type: 'retry' }
    | { type: 'unconfirmed' };

/**
 * Pure decision function, séparée de l'appel réseau pour rester testable sans mock de fetch.
 * "paid" recouvre à la fois le tout premier appel qui confirme (push=true) et une revisite d'un
 * achat déjà tracké (push=false) — même écran de succès dans les deux cas, voir
 * documentation/technique/google-tag-manager.md. "unconfirmed" ne prétend jamais que le paiement
 * a échoué : couvre aussi bien un vrai statut "failed" que l'expiration du nombre de tentatives.
 */
export function decidePollOutcome(response: ConfirmAnalyticsResponse, attempt: number, maxAttempts: number): PollDecision {
    if ('completed' === response.status && null !== response.analytics) {
        return { type: 'paid', push: response.claimed, analytics: response.analytics };
    }

    if ('failed' === response.status || attempt >= maxAttempts) {
        return { type: 'unconfirmed' };
    }

    return { type: 'retry' };
}

/**
 * Le backend est l'unique source des champs envoyés (transactionId/value/currency/itemId/
 * itemName) — jamais générés côté client, jamais de donnée de paiement (carte, e-mail, nom).
 */
export function buildPurchaseEvent(analytics: ConfirmAnalyticsAnalytics): Record<string, unknown> {
    return {
        event: 'purchase',
        transaction_id: analytics.transactionId,
        currency: analytics.currency,
        value: analytics.value,
        items: [
            {
                item_id: analytics.itemId,
                item_name: analytics.itemName,
                item_category: 'credits',
                price: analytics.value,
                quantity: 1,
            },
        ],
    };
}
