<?php

declare(strict_types=1);

namespace App\Usage\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreditAdjustmentRequest
{
    #[Assert\Positive(message: 'The adjustment amount must be a positive number of credits.')]
    public int $amount = 0;
}
