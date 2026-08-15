<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

use App\Routing\Enum\WaypointType;

/**
 * Une étape intermédiaire avec son type (STOP/VIA) et sa position d'origine dans l'itinéraire tel
 * que soumis par l'utilisateur — la position d'origine permet d'afficher un avant/après quand
 * l'optimisation de l'ordre des étapes est demandée (voir RouteOptions::$optimizeWaypointOrder et
 * RouteResult::$optimizedWaypointOrder).
 */
final readonly class RouteWaypoint
{
    public function __construct(
        public RouteLocation $location,
        public WaypointType $type,
        public int $originalPosition,
    ) {
    }

    /**
     * @return array{address: string, via?: true}|array{location: array{latLng: array{latitude: float, longitude: float}}, via?: true}
     */
    public function toGoogleWaypoint(): array
    {
        $waypoint = $this->location->toGoogleWaypoint();

        if (WaypointType::VIA === $this->type) {
            $waypoint['via'] = true;
        }

        return $waypoint;
    }
}
