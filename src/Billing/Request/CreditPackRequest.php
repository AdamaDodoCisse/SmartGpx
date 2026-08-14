<?php

declare(strict_types=1);

namespace App\Billing\Request;

use App\Billing\Enum\CreditPackBadge;
use Symfony\Component\Validator\Constraints as Assert;

final class CreditPackRequest
{
    #[Assert\Positive]
    public int $credits = 0;

    #[Assert\Positive]
    public int $priceCents = 0;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    public string $currency = 'usd';

    public ?CreditPackBadge $badge = null;

    #[Assert\PositiveOrZero]
    public int $displayOrder = 0;

    public bool $active = true;
}
