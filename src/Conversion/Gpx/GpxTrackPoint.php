<?php

declare(strict_types=1);

namespace App\Conversion\Gpx;

final readonly class GpxTrackPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
