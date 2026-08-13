<?php

declare(strict_types=1);

namespace App\Routing\Result;

final readonly class RoutePoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
