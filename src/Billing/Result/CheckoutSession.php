<?php

declare(strict_types=1);

namespace App\Billing\Result;

final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $redirectUrl,
    ) {
    }
}
