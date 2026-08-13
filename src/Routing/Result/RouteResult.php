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
     * @param list<RoutePoint> $points géométrie complète de l'itinéraire, dans l'ordre de parcours
     * @param list<RouteLeg>   $legs   une entrée par segment origine/étape/destination consécutif
     */
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
        public array $points,
        public array $legs,
        public TravelMode $travelMode,
    ) {
    }
}
