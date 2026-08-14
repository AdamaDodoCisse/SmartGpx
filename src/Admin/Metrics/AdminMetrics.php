<?php

declare(strict_types=1);

namespace App\Admin\Metrics;

final readonly class AdminMetrics
{
    public function __construct(
        public int $totalUsers,
        public int $totalCreditsIssued,
        public int $totalCreditsConsumed,
        public int $totalSuccessfulConversions,
        public int $totalFailedConversions,
        public int $totalCompletedPurchases,
        public int $totalRevenueCents,
        public int $activeCreditPackCount,
    ) {
    }
}
