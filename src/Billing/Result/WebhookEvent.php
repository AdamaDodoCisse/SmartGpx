<?php

declare(strict_types=1);

namespace App\Billing\Result;

use App\Billing\Enum\WebhookEventType;

final readonly class WebhookEvent
{
    /**
     * @param array<string, string>|null $metadata
     */
    public function __construct(
        public WebhookEventType $type,
        public ?string $checkoutSessionId,
        public ?string $paymentIntentId,
        public ?array $metadata,
    ) {
    }
}
