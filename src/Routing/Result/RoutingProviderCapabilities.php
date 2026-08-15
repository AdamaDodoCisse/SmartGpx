<?php

declare(strict_types=1);

namespace App\Routing\Result;

use App\Routing\Enum\TravelMode;

/**
 * Ce que le fournisseur de routing actif sait réellement faire. Le frontend n'affiche jamais une
 * option que ces capabilities ne déclarent pas supportée — voir
 * documentation/technique/routing-provider.md. Un futur ValhallaRoutingProvider (voir ADR-001)
 * déclarerait un jeu de capabilities différent, plus restreint, sans qu'aucune interface ne
 * change.
 */
final readonly class RoutingProviderCapabilities
{
    /**
     * @param list<TravelMode> $supportedTravelModes
     */
    public function __construct(
        public array $supportedTravelModes,
        public bool $avoidHighways,
        public bool $avoidTolls,
        public bool $avoidFerries,
        public bool $trafficAware,
        public bool $trafficAwareOptimal,
        public bool $waypointOptimization,
        public bool $alternativeRoutes,
        public bool $fuelEfficientRoute,
        public bool $tollEstimation,
        public int $maxIntermediateWaypoints,
    ) {
    }

    /**
     * @return array{
     *     supportedTravelModes: list<string>, avoidHighways: bool, avoidTolls: bool, avoidFerries: bool,
     *     trafficAware: bool, trafficAwareOptimal: bool, waypointOptimization: bool, alternativeRoutes: bool,
     *     fuelEfficientRoute: bool, tollEstimation: bool, maxIntermediateWaypoints: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'supportedTravelModes' => array_map(static fn (TravelMode $mode): string => $mode->value, $this->supportedTravelModes),
            'avoidHighways' => $this->avoidHighways,
            'avoidTolls' => $this->avoidTolls,
            'avoidFerries' => $this->avoidFerries,
            'trafficAware' => $this->trafficAware,
            'trafficAwareOptimal' => $this->trafficAwareOptimal,
            'waypointOptimization' => $this->waypointOptimization,
            'alternativeRoutes' => $this->alternativeRoutes,
            'fuelEfficientRoute' => $this->fuelEfficientRoute,
            'tollEstimation' => $this->tollEstimation,
            'maxIntermediateWaypoints' => $this->maxIntermediateWaypoints,
        ];
    }
}
