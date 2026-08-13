<?php

declare(strict_types=1);

namespace App\Conversion\Gpx;

/**
 * Découplé de l'entité Doctrine Conversion pour rester testable sans base de données.
 */
final readonly class GpxRouteData
{
    /**
     * @param list<GpxWaypoint>   $waypoints
     * @param list<GpxTrackPoint> $trackPoints
     */
    public function __construct(
        public string $routeName,
        public array $waypoints,
        public array $trackPoints,
    ) {
    }
}
