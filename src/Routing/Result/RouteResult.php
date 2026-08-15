<?php

declare(strict_types=1);

namespace App\Routing\Result;

use App\Routing\Enum\TravelMode;

/**
 * Résultat d'itinéraire indépendant du fournisseur : aucun type spécifique à Google ne doit
 * jamais apparaître ici (voir GoogleRoutesProvider, seul point de traduction).
 */
final readonly class RouteResult
{
    /**
     * @param list<RoutePoint> $points                 géométrie complète de l'itinéraire, dans l'ordre de parcours
     * @param list<RouteLeg>   $legs                   une entrée par segment origine/étape/destination consécutif
     * @param string|null      $routeLabel             'FASTEST'|'FUEL_EFFICIENT'|'ALTERNATE', null pour un itinéraire unique (pas d'alternatives demandées)
     * @param list<int>|null   $optimizedWaypointOrder permutation des index d'étapes intermédiaires d'origine, null si l'optimisation n'a pas été demandée
     */
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
        public array $points,
        public array $legs,
        public TravelMode $travelMode,
        public ?string $routeLabel = null,
        public ?array $optimizedWaypointOrder = null,
        public ?RouteTollEstimate $tollEstimate = null,
    ) {
    }
}
