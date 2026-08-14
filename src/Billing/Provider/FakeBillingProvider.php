<?php

declare(strict_types=1);

namespace App\Billing\Provider;

use App\Billing\Enum\WebhookEventType;
use App\Billing\Exception\BillingProviderException;
use App\Billing\Result\CheckoutSession;
use App\Billing\Result\WebhookEvent;

/**
 * Implémentation déterministe et scriptable de BillingProviderInterface, utilisée dans les tests
 * (voir config/services.yaml, alias when@test) — aucun test ne doit jamais appeler la vraie API
 * Stripe.
 */
final class FakeBillingProvider implements BillingProviderInterface
{
    /** @var list<CheckoutSession|BillingProviderException> */
    private array $checkoutQueue = [];

    /** @var list<WebhookEvent|BillingProviderException> */
    private array $webhookQueue = [];

    public int $checkoutCallCount = 0;

    public int $webhookCallCount = 0;

    public function queue(CheckoutSession|BillingProviderException $outcome): void
    {
        $this->checkoutQueue[] = $outcome;
    }

    public function queueWebhookEvent(WebhookEvent|BillingProviderException $outcome): void
    {
        $this->webhookQueue[] = $outcome;
    }

    public function createCheckoutSession(
        string $customerEmail,
        int $amountCents,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
        string $idempotencyKey,
    ): CheckoutSession {
        ++$this->checkoutCallCount;

        $outcome = array_shift($this->checkoutQueue) ?? self::defaultFixtureCheckoutSession();

        if ($outcome instanceof BillingProviderException) {
            throw $outcome;
        }

        return $outcome;
    }

    public function parseWebhookEvent(string $rawPayload, ?string $signatureHeader): WebhookEvent
    {
        ++$this->webhookCallCount;

        $outcome = array_shift($this->webhookQueue) ?? self::defaultFixtureWebhookEvent();

        if ($outcome instanceof BillingProviderException) {
            throw $outcome;
        }

        return $outcome;
    }

    public static function defaultFixtureCheckoutSession(): CheckoutSession
    {
        return new CheckoutSession(id: 'cs_test_fixture', redirectUrl: 'https://checkout.stripe.com/c/pay/cs_test_fixture');
    }

    public static function defaultFixtureWebhookEvent(): WebhookEvent
    {
        return new WebhookEvent(
            WebhookEventType::CHECKOUT_SESSION_COMPLETED,
            checkoutSessionId: 'cs_test_fixture',
            paymentIntentId: 'pi_test_fixture',
            metadata: null,
        );
    }

    public function reset(): void
    {
        $this->checkoutQueue = [];
        $this->webhookQueue = [];
        $this->checkoutCallCount = 0;
        $this->webhookCallCount = 0;
    }
}
