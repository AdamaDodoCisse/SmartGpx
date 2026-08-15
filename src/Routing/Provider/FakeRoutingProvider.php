<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\RoutingFeatureCostTier;
use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RoutingProviderException;
use App\Routing\Result\RouteComputation;
use App\Routing\Result\RouteLeg;
use App\Routing\Result\RoutePoint;
use App\Routing\Result\RouteResult;
use App\Routing\Result\RouteTollEstimate;
use App\Routing\Result\RoutingProviderCapabilities;
use App\Routing\Service\RouteOptionsCostClassifier;
use App\Routing\ValueObject\RouteLocation;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\RouteWaypoint;

/**
 * Implémentation déterministe et scriptable de RoutingProviderInterface, utilisée dans les tests
 * (voir config/services.yaml, alias when@test) — aucun test ne doit jamais appeler la vraie API
 * Google Routes.
 */
final class FakeRoutingProvider implements RoutingProviderInterface
{
    /** @var list<RouteComputation|RoutingProviderException> */
    private array $queue = [];

    public int $callCount = 0;

    /**
     * @var list<RouteOptions>
     */
    public array $receivedOptions = [];

    public function __construct(
        private readonly RouteOptionsCostClassifier $costClassifier = new RouteOptionsCostClassifier(),
    ) {
    }

    public function queue(RouteComputation|RouteResult|RoutingProviderException $outcome): void
    {
        $this->queue[] = $outcome instanceof RouteResult
            ? new RouteComputation([$outcome], RoutingFeatureCostTier::STANDARD)
            : $outcome;
    }

    /**
     * @param list<RouteWaypoint> $intermediates
     */
    public function computeRoutes(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
        RouteOptions $options = new RouteOptions(),
    ): RouteComputation {
        ++$this->callCount;
        $this->receivedOptions[] = $options;

        $outcome = array_shift($this->queue) ?? new RouteComputation(
            $options->requestsMultipleRoutes()
                ? self::defaultMultiRouteFixture($travelMode)
                : [self::defaultFixtureRoute($travelMode)],
            $this->costClassifier->classify($options),
        );

        if ($outcome instanceof RoutingProviderException) {
            throw $outcome;
        }

        return $outcome;
    }

    public function capabilities(): RoutingProviderCapabilities
    {
        return new RoutingProviderCapabilities(
            supportedTravelModes: [TravelMode::DRIVE, TravelMode::TWO_WHEELER, TravelMode::BICYCLE, TravelMode::WALK, TravelMode::TRANSIT],
            avoidHighways: true,
            avoidTolls: true,
            avoidFerries: true,
            trafficAware: true,
            trafficAwareOptimal: true,
            waypointOptimization: true,
            alternativeRoutes: true,
            fuelEfficientRoute: true,
            tollEstimation: true,
            maxIntermediateWaypoints: 25,
        );
    }

    public static function defaultFixtureRoute(TravelMode $travelMode): RouteResult
    {
        $start = new RoutePoint(48.8566, 2.3522);
        $middle = new RoutePoint(48.9, 2.4);
        $end = new RoutePoint(49.051624, 2.0093594);

        return new RouteResult(
            distanceMeters: 12345,
            durationSeconds: 900,
            points: [$start, $middle, $end],
            legs: [new RouteLeg(12345, 900, $start, $end)],
            travelMode: $travelMode,
            routeLabel: 'FASTEST',
        );
    }

    /**
     * @return list<RouteResult>
     */
    public static function defaultMultiRouteFixture(TravelMode $travelMode): array
    {
        $primary = self::defaultFixtureRoute($travelMode);

        $altStart = new RoutePoint(48.8566, 2.3522);
        $altEnd = new RoutePoint(49.051624, 2.0093594);
        $alternate = new RouteResult(
            distanceMeters: 13100,
            durationSeconds: 970,
            points: [$altStart, $altEnd],
            legs: [new RouteLeg(13100, 970, $altStart, $altEnd)],
            travelMode: $travelMode,
            routeLabel: 'ALTERNATE',
        );

        $ecoStart = new RoutePoint(48.8566, 2.3522);
        $ecoEnd = new RoutePoint(49.051624, 2.0093594);
        $ecoRoute = new RouteResult(
            distanceMeters: 12800,
            durationSeconds: 930,
            points: [$ecoStart, $ecoEnd],
            legs: [new RouteLeg(12800, 930, $ecoStart, $ecoEnd)],
            travelMode: $travelMode,
            routeLabel: 'FUEL_EFFICIENT',
            tollEstimate: new RouteTollEstimate('EUR', 4.5),
        );

        return [$primary, $alternate, $ecoRoute];
    }

    public function reset(): void
    {
        $this->queue = [];
        $this->callCount = 0;
        $this->receivedOptions = [];
    }
}
