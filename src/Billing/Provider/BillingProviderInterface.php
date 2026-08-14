<?php

declare(strict_types=1);

namespace App\Billing\Provider;

use App\Billing\Exception\BillingProviderUnavailableException;
use App\Billing\Exception\InvalidWebhookSignatureException;
use App\Billing\Result\CheckoutSession;
use App\Billing\Result\WebhookEvent;

/**
 * Frontière externe : la seule façade que le reste de l'application utilise pour créer une
 * session de paiement et interpréter les notifications du prestataire. Aucun type spécifique à
 * un fournisseur (Stripe ou autre) ne doit fuiter à travers cette interface — voir
 * StripeBillingProvider et FakeBillingProvider, et
 * documentation/decisions/ADR-006-billing-provider.md.
 */
interface BillingProviderInterface
{
    /**
     * @param array<string, string> $metadata
     *
     * @throws BillingProviderUnavailableException
     */
    public function createCheckoutSession(
        string $customerEmail,
        int $amountCents,
        string $currency,
        string $productName,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
        string $idempotencyKey,
    ): CheckoutSession;

    /**
     * @throws InvalidWebhookSignatureException
     */
    public function parseWebhookEvent(string $rawPayload, ?string $signatureHeader): WebhookEvent;
}
