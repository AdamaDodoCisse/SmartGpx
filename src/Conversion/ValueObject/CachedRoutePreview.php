<?php

declare(strict_types=1);

namespace App\Conversion\ValueObject;

use App\Conversion\Parser\ParsedGoogleMapsUrl;
use App\Routing\Enum\WaypointType;
use App\Routing\Result\RouteComputation;
use App\Routing\ValueObject\RouteOptions;

/**
 * Payload sérialisé dans le cache par RoutePreviewStore — tout ce dont
 * ExportPreviewedRouteAction a besoin pour construire un Conversion sans recalculer l'itinéraire
 * ni le refacturer.
 */
final readonly class CachedRoutePreview
{
    /**
     * @param list<WaypointType> $waypointTypes
     */
    public function __construct(
        public int $userId,
        public string $sourceUrl,
        public ParsedGoogleMapsUrl $parsed,
        public RouteComputation $computation,
        public RouteOptions $options,
        public array $waypointTypes,
    ) {
    }
}
