<?php

declare(strict_types=1);

namespace App\Tests\Billing;

use App\Billing\CreditPackSlug;
use PHPUnit\Framework\TestCase;

final class CreditPackSlugTest extends TestCase
{
    public function testKnownLaunchGridTiersResolveToTheirSlug(): void
    {
        self::assertSame('starter_10', CreditPackSlug::forCredits(10));
        self::assertSame('popular_100', CreditPackSlug::forCredits(100));
        self::assertSame('power_500', CreditPackSlug::forCredits(500));
    }

    public function testAnUnknownTierFallsBackToAGenericSlugInsteadOfErroring(): void
    {
        self::assertSame('pack_42', CreditPackSlug::forCredits(42));
    }
}
