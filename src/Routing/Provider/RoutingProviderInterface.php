<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Result\RouteResult;
use App\Routing\ValueObject\RouteLocation;

/**
 * Frontière externe : la seule façade que le reste de l'application utilise pour calculer un
 * itinéraire. Aucun type spécifique à un fournisseur (Google ou autre) ne doit fuiter à travers
 * cette interface — voir GoogleRoutesProvider et FakeRoutingProvider.
 */
interface RoutingProviderInterface
{
    /**
     * @param list<RouteLocation> $intermediates
     *
     * @throws RouteNotFoundException
     * @throws RoutingProviderUnavailableException
     */
    public function computeRoute(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
    ): RouteResult;
}
