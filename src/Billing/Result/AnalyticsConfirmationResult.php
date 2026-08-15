<?php

declare(strict_types=1);

namespace App\Billing\Result;

use App\Billing\Enum\CreditPurchaseStatus;

final readonly class AnalyticsConfirmationResult
{
    public function __construct(
        public CreditPurchaseStatus $status,
        public bool $claimed,
        public ?string $transactionId = null,
        public ?float $value = null,
        public ?string $currency = null,
        public ?int $credits = null,
        public ?string $itemId = null,
        public ?string $itemName = null,
    ) {
    }
}
