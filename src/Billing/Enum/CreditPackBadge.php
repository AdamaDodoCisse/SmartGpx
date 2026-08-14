<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum CreditPackBadge: string
{
    case MOST_POPULAR = 'most_popular';
    case BEST_VALUE = 'best_value';
}
