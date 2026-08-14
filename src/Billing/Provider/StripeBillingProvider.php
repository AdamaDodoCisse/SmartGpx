<?php

declare(strict_types=1);

namespace App\Billing\Provider;

use App\Billing\Enum\WebhookEventType;
use App\Billing\Exception\BillingProviderUnavailableException;
use App\Billing\Exception\InvalidWebhookSignatureException;
use App\Billing\Result\CheckoutSession;
use App\Billing\Result\WebhookEvent;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implémentation Stripe (Checkout hébergé + webhooks) de BillingProviderInterface. Aucun type
 * \Stripe\* ne doit fuiter hors de cette classe — voir
 * documentation/decisions/ADR-006-billing-provider.md.
 */
final class StripeBillingProvider implements BillingProviderInterface
{
    public function __construct(
        private readonly StripeClient $stripeClient,
        #[Autowire(env: 'string:STRIPE_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
        private readonly LoggerInterface $logger,
    ) {
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
        try {
            $session = $this->stripeClient->checkout->sessions->create(
                [
                    'mode' => 'payment',
                    'customer_email' => $customerEmail,
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $amountCents,
                            'product_data' => ['name' => $productName],
                        ],
                    ]],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => $metadata,
                ],
                ['idempotency_key' => $idempotencyKey],
            );
        } catch (ApiErrorException $exception) {
            $this->logger->error('Stripe checkout session creation failed.', ['exception' => $exception]);

            throw new BillingProviderUnavailableException('Unable to reach the billing provider.', previous: $exception);
        }

        if (!\is_string($session->url) || '' === $session->url) {
            $this->logger->error('Stripe checkout session has no redirect URL.', ['sessionId' => $session->id]);

            throw new BillingProviderUnavailableException('Billing provider returned no redirect URL.');
        }

        return new CheckoutSession(id: $session->id, redirectUrl: $session->url);
    }

    public function parseWebhookEvent(string $rawPayload, ?string $signatureHeader): WebhookEvent
    {
        if (null === $signatureHeader) {
            throw new InvalidWebhookSignatureException('Missing Stripe-Signature header.');
        }

        try {
            $event = Webhook::constructEvent($rawPayload, $signatureHeader, $this->webhookSecret);
        } catch (\UnexpectedValueException|SignatureVerificationException $exception) {
            throw new InvalidWebhookSignatureException('Invalid Stripe webhook signature.', previous: $exception);
        }

        if ('checkout.session.completed' !== $event->type) {
            return new WebhookEvent(WebhookEventType::UNHANDLED, checkoutSessionId: null, paymentIntentId: null, metadata: null);
        }

        $session = $event->data->object;

        if (!\is_string($session->id ?? null)) {
            // Ne devrait jamais arriver : Stripe garantit un id sur l'objet Checkout Session.
            // Non signalé dans l'interface (@throws) car ce n'est pas un échec de signature —
            // remonte tel quel, devient un 500 côté contrôleur, Stripe réessaiera.
            throw new \UnexpectedValueException('Stripe checkout.session.completed payload is missing an id.');
        }

        $paymentIntent = $session->payment_intent ?? null;
        $paymentIntentId = match (true) {
            \is_string($paymentIntent) => $paymentIntent,
            $paymentIntent instanceof PaymentIntent => $paymentIntent->id,
            default => null,
        };

        $metadata = $session->metadata ?? null;

        return new WebhookEvent(
            WebhookEventType::CHECKOUT_SESSION_COMPLETED,
            checkoutSessionId: $session->id,
            paymentIntentId: $paymentIntentId,
            metadata: $metadata instanceof \Stripe\StripeObject ? $metadata->toArray() : null,
        );
    }
}
