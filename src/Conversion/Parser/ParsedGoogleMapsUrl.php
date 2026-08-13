<?php

declare(strict_types=1);

namespace App\Conversion\Parser;

use App\Routing\Enum\TravelMode;
use App\Routing\ValueObject\RouteLocation;

final readonly class ParsedGoogleMapsUrl
{
    /**
     * @param list<RouteLocation> $intermediates
     */
    public function __construct(
        public RouteLocation $origin,
        public RouteLocation $destination,
        public array $intermediates,
        public TravelMode $travelMode,
        public bool $travelModeInferred,
    ) {
    }
}
