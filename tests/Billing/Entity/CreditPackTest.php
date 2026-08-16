<?php

declare(strict_types=1);

namespace App\Tests\Billing\Entity;

use App\Billing\Entity\CreditPack;
use PHPUnit\Framework\TestCase;

final class CreditPackTest extends TestCase
{
    public function testAnalyticsSlugIsDerivedFromCredits(): void
    {
        $pack = new CreditPack(credits: 500, priceCents: 2999, currency: 'usd', badge: null, displayOrder: 3);

        self::assertSame('power_500', $pack->getAnalyticsSlug());
    }
}
