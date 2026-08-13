<?php

declare(strict_types=1);

namespace App\Conversion\Gpx;

final readonly class GpxWaypoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $name,
        public string $type,
    ) {
    }
}
