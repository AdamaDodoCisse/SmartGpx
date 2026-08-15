<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Exception\TooManyWaypointsException;
use App\Routing\Result\RouteComputation;
use App\Routing\Result\RoutingProviderCapabilities;
use App\Routing\ValueObject\RouteLocation;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\RouteWaypoint;

/**
 * Frontière externe : la seule façade que le reste de l'application utilise pour calculer un
 * itinéraire. Aucun type spécifique à un fournisseur (Google ou autre) ne doit fuiter à travers
 * cette interface — voir GoogleRoutesProvider et FakeRoutingProvider.
 *
 * `capabilities()` déclare ce que le fournisseur actif sait réellement faire ; le reste de
 * l'application (et le frontend, via RoutingCapabilitiesController) n'affiche/n'envoie jamais une
 * option que ces capabilities ne déclarent pas supportée. Voir
 * documentation/technique/routing-provider.md.
 */
interface RoutingProviderInterface
{
    /**
     * @param list<RouteWaypoint> $intermediates
     *
     * @throws RouteNotFoundException
     * @throws RoutingProviderUnavailableException
     * @throws TooManyWaypointsException
     */
    public function computeRoutes(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
        RouteOptions $options = new RouteOptions(),
    ): RouteComputation;

    public function capabilities(): RoutingProviderCapabilities;
}
