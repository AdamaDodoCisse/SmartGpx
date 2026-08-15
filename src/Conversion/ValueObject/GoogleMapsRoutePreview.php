<?php

declare(strict_types=1);

namespace App\Conversion\ValueObject;

use App\Routing\Result\RouteComputation;

final readonly class GoogleMapsRoutePreview
{
    public function __construct(
        public string $previewId,
        public RouteComputation $computation,
    ) {
    }
}
