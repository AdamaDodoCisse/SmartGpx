<?php

declare(strict_types=1);

namespace App\Routing\Result;

use App\Routing\Enum\RoutingFeatureCostTier;

/**
 * Résultat complet d'un appel à RoutingProviderInterface::computeRoutes() : un ou plusieurs
 * itinéraires candidats (le premier est toujours l'itinéraire principal/recommandé — alternatives
 * et route de référence économe en carburant, si demandées, viennent ensuite) et la classification
 * de coût appliquée à la requête effectivement envoyée.
 */
final readonly class RouteComputation
{
    /**
     * @param list<RouteResult> $routes premier élément = itinéraire principal
     */
    public function __construct(
        public array $routes,
        public RoutingFeatureCostTier $costTier,
    ) {
    }

    public function primary(): RouteResult
    {
        return $this->routes[0];
    }

    public function hasAlternatives(): bool
    {
        return \count($this->routes) > 1;
    }
}
