<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

use App\Routing\Enum\TravelMode;

final readonly class RoutePresetResolution
{
    public function __construct(
        public RouteOptions $options,
        public ?TravelMode $travelMode,
    ) {
    }
}
