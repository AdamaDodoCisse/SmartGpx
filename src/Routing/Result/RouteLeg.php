<?php

declare(strict_types=1);

namespace App\Routing\Result;

/**
 * Une portion d'itinéraire entre deux waypoints consécutifs (origine→étape, étape→étape,
 * étape→destination). startPoint/endPoint sont toujours des coordonnées résolues par le
 * fournisseur, même quand le waypoint d'origine était une adresse littérale — utile pour générer
 * des <wpt> GPX sans appel de géocodage supplémentaire.
 */
final readonly class RouteLeg
{
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
        public RoutePoint $startPoint,
        public RoutePoint $endPoint,
    ) {
    }
}
