<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RoutingProviderException;
use App\Routing\Result\RouteLeg;
use App\Routing\Result\RoutePoint;
use App\Routing\Result\RouteResult;
use App\Routing\ValueObject\RouteLocation;

/**
 * Implémentation déterministe et scriptable de RoutingProviderInterface, utilisée dans les tests
 * (voir config/services.yaml, alias when@test) — aucun test ne doit jamais appeler la vraie API
 * Google Routes.
 */
final class FakeRoutingProvider implements RoutingProviderInterface
{
    /** @var list<RouteResult|RoutingProviderException> */
    private array $queue = [];

    public int $callCount = 0;

    public function queue(RouteResult|RoutingProviderException $outcome): void
    {
        $this->queue[] = $outcome;
    }

    public function computeRoute(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
    ): RouteResult {
        ++$this->callCount;

        $outcome = array_shift($this->queue) ?? self::defaultFixtureRoute($travelMode);

        if ($outcome instanceof RoutingProviderException) {
            throw $outcome;
        }

        return $outcome;
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
        );
    }

    public function reset(): void
    {
        $this->queue = [];
        $this->callCount = 0;
    }
}
