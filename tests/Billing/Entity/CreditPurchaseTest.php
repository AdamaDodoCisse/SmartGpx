<?php

declare(strict_types=1);

namespace App\Tests\Billing\Entity;

use App\Billing\Entity\CreditPack;
use App\Billing\Entity\CreditPurchase;
use App\Billing\Enum\CreditPurchaseStatus;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;

final class CreditPurchaseTest extends TestCase
{
    public function testItSnapshotsThePackAtConstructionTime(): void
    {
        $pack = new CreditPack(credits: 100, priceCents: 999, currency: 'usd', badge: null, displayOrder: 1);
        $purchase = new CreditPurchase(new User('buyer@example.com'), $pack, 'cs_test_123');

        self::assertSame(100, $purchase->getCredits());
        self::assertSame(999, $purchase->getAmountCents());
        self::assertSame('usd', $purchase->getCurrency());
        self::assertSame('cs_test_123', $purchase->getStripeCheckoutSessionId());
        self::assertSame(CreditPurchaseStatus::PENDING, $purchase->getStatus());
        self::assertFalse($purchase->isCompleted());
        self::assertNull($purchase->getCompletedAt());
    }

    public function testMarkCompletedTransitionsStatusAndSetsCompletedAt(): void
    {
        $pack = new CreditPack(credits: 100, priceCents: 999, currency: 'usd', badge: null, displayOrder: 1);
        $purchase = new CreditPurchase(new User('buyer@example.com'), $pack, 'cs_test_123');

        $purchase->markCompleted();

        self::assertTrue($purchase->isCompleted());
        self::assertNotNull($purchase->getCompletedAt());
    }

    public function testMarkCompletedIsIdempotent(): void
    {
        $pack = new CreditPack(credits: 100, priceCents: 999, currency: 'usd', badge: null, displayOrder: 1);
        $purchase = new CreditPurchase(new User('buyer@example.com'), $pack, 'cs_test_123');

        $purchase->markCompleted();
        $firstCompletedAt = $purchase->getCompletedAt();
        $purchase->markCompleted();

        self::assertSame($firstCompletedAt, $purchase->getCompletedAt());
    }

    public function testMarkAnalyticsTrackedReturnsTrueOnlyOnce(): void
    {
        $pack = new CreditPack(credits: 100, priceCents: 999, currency: 'usd', badge: null, displayOrder: 1);
        $purchase = new CreditPurchase(new User('buyer@example.com'), $pack, 'cs_test_123');

        self::assertFalse($purchase->isAnalyticsTracked());
        self::assertNull($purchase->getAnalyticsTrackedAt());

        self::assertTrue($purchase->markAnalyticsTracked());
        $firstTrackedAt = $purchase->getAnalyticsTrackedAt();
        self::assertTrue($purchase->isAnalyticsTracked());

        self::assertFalse($purchase->markAnalyticsTracked());
        self::assertSame($firstTrackedAt, $purchase->getAnalyticsTrackedAt());
    }

    public function testStripePaymentIntentIdCanBeSetAfterConstruction(): void
    {
        $pack = new CreditPack(credits: 100, priceCents: 999, currency: 'usd', badge: null, displayOrder: 1);
        $purchase = new CreditPurchase(new User('buyer@example.com'), $pack, 'cs_test_123');

        self::assertNull($purchase->getStripePaymentIntentId());

        $purchase->setStripePaymentIntentId('pi_test_456');

        self::assertSame('pi_test_456', $purchase->getStripePaymentIntentId());
    }
}
