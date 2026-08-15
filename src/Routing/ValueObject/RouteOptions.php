<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

use App\Routing\Enum\RouteDetail;
use App\Routing\Enum\RoutingPreference;

/**
 * Options avancées de calcul d'itinéraire, indépendantes du fournisseur — jamais de nom
 * spécifique à Google (avoidHighways, pas GoogleAvoidHighways), voir
 * documentation/decisions/ADR-008-routing-provider-capabilities.md. Chaque valeur par défaut
 * reproduit exactement le comportement d'avant les options avancées : `new RouteOptions()`
 * calcule le même itinéraire qu'un appel à computeRoutes() avant cette fonctionnalité.
 */
final readonly class RouteOptions
{
    public function __construct(
        public RoutingPreference $routingPreference = RoutingPreference::TRAFFIC_UNAWARE,
        public RouteModifiers $modifiers = new RouteModifiers(),
        public bool $optimizeWaypointOrder = false,
        public RouteDetail $routeDetail = RouteDetail::STANDARD,
        public bool $computeAlternativeRoutes = false,
        public bool $showFuelEfficientRoute = false,
        public bool $showTollEstimates = false,
        public ?VehicleProfile $vehicleProfile = null,
    ) {
    }

    public function requestsMultipleRoutes(): bool
    {
        return $this->computeAlternativeRoutes || $this->showFuelEfficientRoute;
    }
}
