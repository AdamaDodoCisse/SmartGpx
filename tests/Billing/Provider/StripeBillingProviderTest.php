<?php

declare(strict_types=1);

namespace App\Tests\Billing\Provider;

use App\Billing\Enum\WebhookEventType;
use App\Billing\Exception\InvalidWebhookSignatureException;
use App\Billing\Provider\StripeBillingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stripe\StripeClient;

final class StripeBillingProviderTest extends TestCase
{
    private const string WEBHOOK_SECRET = 'whsec_test_secret';

    public function testParseWebhookEventAcceptsARealCorrectlySignedPayload(): void
    {
        $payload = $this->checkoutSessionCompletedPayload();
        $provider = $this->createProvider();

        $event = $provider->parseWebhookEvent($payload, $this->signPayload($payload));

        self::assertSame(WebhookEventType::CHECKOUT_SESSION_COMPLETED, $event->type);
        self::assertSame('cs_test_abc123', $event->checkoutSessionId);
        self::assertSame('pi_test_xyz789', $event->paymentIntentId);
        self::assertSame(['creditPackPublicId' => '0199abc'], $event->metadata);
    }

    public function testParseWebhookEventReturnsUnhandledForAnUninterestingEventType(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_test_1']],
        ], \JSON_THROW_ON_ERROR);

        $provider = $this->createProvider();
        $event = $provider->parseWebhookEvent($payload, $this->signPayload($payload));

        self::assertSame(WebhookEventType::UNHANDLED, $event->type);
        self::assertNull($event->checkoutSessionId);
    }

    public function testParseWebhookEventRejectsAMissingSignatureHeader(): void
    {
        $provider = $this->createProvider();

        $this->expectException(InvalidWebhookSignatureException::class);
        $provider->parseWebhookEvent($this->checkoutSessionCompletedPayload(), null);
    }

    public function testParseWebhookEventRejectsAnIncorrectSignature(): void
    {
        $provider = $this->createProvider();

        $this->expectException(InvalidWebhookSignatureException::class);
        $provider->parseWebhookEvent($this->checkoutSessionCompletedPayload(), 't=1700000000,v1=deadbeef');
    }

    public function testParseWebhookEventRejectsASignatureComputedWithTheWrongSecret(): void
    {
        $payload = $this->checkoutSessionCompletedPayload();
        $timestamp = time();
        $wrongSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_a_different_secret');
        $provider = $this->createProvider();

        $this->expectException(InvalidWebhookSignatureException::class);
        $provider->parseWebhookEvent($payload, "t={$timestamp},v1={$wrongSignature}");
    }

    private function createProvider(): StripeBillingProvider
    {
        return new StripeBillingProvider(new StripeClient('sk_test_fake'), self::WEBHOOK_SECRET, new NullLogger());
    }

    private function signPayload(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    private function checkoutSessionCompletedPayload(): string
    {
        return json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_abc123',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_test_xyz789',
                    'metadata' => ['creditPackPublicId' => '0199abc'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
