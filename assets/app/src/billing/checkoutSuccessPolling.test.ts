import { describe, expect, it } from 'vitest';
import { buildPurchaseEvent, decidePollOutcome, type ConfirmAnalyticsResponse } from './checkoutSuccessPolling';

const ANALYTICS = {
    transactionId: 'smartgpx_00000000-0000-7000-8000-000000000000',
    value: 9.99,
    currency: 'USD',
    credits: 100,
    itemId: '11111111-1111-7111-8111-111111111111',
    itemName: '100 SmartGPX Credits',
};

describe('decidePollOutcome', () => {
    it('pushes when completed and claimed by this call', () => {
        const response: ConfirmAnalyticsResponse = { status: 'completed', claimed: true, analytics: ANALYTICS };

        expect(decidePollOutcome(response, 1, 8)).toEqual({ type: 'paid', push: true, analytics: ANALYTICS });
    });

    it('does not push when completed but already tracked by a previous call', () => {
        const response: ConfirmAnalyticsResponse = { status: 'completed', claimed: false, analytics: ANALYTICS };

        expect(decidePollOutcome(response, 1, 8)).toEqual({ type: 'paid', push: false, analytics: ANALYTICS });
    });

    it('retries while pending and under the attempt limit', () => {
        const response: ConfirmAnalyticsResponse = { status: 'pending', claimed: false, analytics: null };

        expect(decidePollOutcome(response, 3, 8)).toEqual({ type: 'retry' });
    });

    it('gives up as unconfirmed once the attempt limit is reached, without claiming failure', () => {
        const response: ConfirmAnalyticsResponse = { status: 'pending', claimed: false, analytics: null };

        expect(decidePollOutcome(response, 8, 8)).toEqual({ type: 'unconfirmed' });
    });

    it('treats a real failed status as unconfirmed too, never as a distinct alarming state', () => {
        const response: ConfirmAnalyticsResponse = { status: 'failed', claimed: false, analytics: null };

        expect(decidePollOutcome(response, 1, 8)).toEqual({ type: 'unconfirmed' });
    });
});

describe('buildPurchaseEvent', () => {
    it('builds a GA4-shaped ecommerce payload from backend-supplied fields only', () => {
        const event = buildPurchaseEvent(ANALYTICS);

        expect(event).toEqual({
            event: 'purchase',
            transaction_id: ANALYTICS.transactionId,
            currency: ANALYTICS.currency,
            value: ANALYTICS.value,
            items: [
                {
                    item_id: ANALYTICS.itemId,
                    item_name: ANALYTICS.itemName,
                    item_category: 'credits',
                    price: ANALYTICS.value,
                    quantity: 1,
                },
            ],
        });
    });

    it('never includes payment or personal details in the payload', () => {
        const event = buildPurchaseEvent(ANALYTICS);
        const item = (event.items as Record<string, unknown>[])[0];

        // Allowlist the exact keys expected at each level, rather than a substring search
        // (which would false-positive on legitimate keys like item_name/item_id).
        expect(Object.keys(event).sort()).toEqual(['currency', 'event', 'items', 'transaction_id', 'value'].sort());
        expect(Object.keys(item).sort()).toEqual(['item_category', 'item_id', 'item_name', 'price', 'quantity'].sort());

        const serialized = JSON.stringify(event).toLowerCase();
        for (const forbidden of ['email', 'card_number', 'address', 'stripe', 'secret', '@']) {
            expect(serialized).not.toContain(forbidden);
        }
    });

    it('includes landing_page when attribution is present', () => {
        const event = buildPurchaseEvent(ANALYTICS, 'guide_google_maps_garmin');

        expect(event.landing_page).toBe('guide_google_maps_garmin');
    });

    it('omits landing_page entirely when there is no attribution', () => {
        const event = buildPurchaseEvent(ANALYTICS);

        expect(Object.keys(event)).not.toContain('landing_page');
    });
});
