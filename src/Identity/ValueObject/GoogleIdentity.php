<?php

declare(strict_types=1);

namespace App\Identity\ValueObject;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $googleId,
        public string $email,
        public bool $emailVerified,
    ) {
    }
}
