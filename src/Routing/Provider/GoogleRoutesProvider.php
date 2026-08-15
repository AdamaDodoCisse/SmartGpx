<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\RouteDetail;
use App\Routing\Enum\RoutingPreference;
use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Exception\TooManyWaypointsException;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation Google Routes API v2 (computeRoutes) de RoutingProviderInterface.
 * Aucun type ni message d'erreur spécifique à Google ne doit fuiter hors de cette classe —
 * voir documentation/decisions/ADR-001-routing-provider.md et ADR-008.
 *
 * routingPreference/routeModifiers/requestedReferenceRoutes/extraComputations ne sont envoyés que
 * pour les modes de transport qui les supportent réellement côté Google (DRIVE/TWO_WHEELER pour
 * les trois premiers, DRIVE seul pour la route de référence économe en carburant) — jamais
 * envoyés pour WALK/BICYCLE/TRANSIT, conformément à la règle "ne jamais envoyer une option que le
 * fournisseur ne supporte pas pour la requête donnée".
 */
final class GoogleRoutesProvider implements RoutingProviderInterface
{
    private const int MAX_INTERMEDIATE_WAYPOINTS = 25;

    private const array TRAFFIC_AND_MODIFIER_CAPABLE_MODES = [TravelMode::DRIVE, TravelMode::TWO_WHEELER];

    private const string FIELD_MASK = 'routes.routeLabels,routes.distanceMeters,routes.duration,routes.polyline.geoJsonLinestring,'
        .'routes.legs.distanceMeters,routes.legs.duration,routes.legs.startLocation,routes.legs.endLocation,'
        .'routes.optimizedIntermediateWaypointIndex,routes.travelAdvisory.tollInfo';

    public function __construct(
        #[Autowire(service: 'google.routes.client')]
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'string:GOOGLE_ROUTES_API_KEY')]
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
        private readonly RouteOptionsCostClassifier $costClassifier,
    ) {
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
        if (\count($intermediates) > self::MAX_INTERMEDIATE_WAYPOINTS) {
            throw new TooManyWaypointsException(sprintf('At most %d intermediate waypoints are supported, %d given.', self::MAX_INTERMEDIATE_WAYPOINTS, \count($intermediates)));
        }

        $supportsTrafficAndModifiers = \in_array($travelMode, self::TRAFFIC_AND_MODIFIER_CAPABLE_MODES, true);

        $body = [
            'origin' => $origin->toGoogleWaypoint(),
            'destination' => $destination->toGoogleWaypoint(),
            'travelMode' => $travelMode->value,
            'polylineEncoding' => 'GEO_JSON_LINESTRING',
            'polylineQuality' => RouteDetail::HIGH_QUALITY === $options->routeDetail ? 'HIGH_QUALITY' : 'OVERVIEW',
        ];

        if ([] !== $intermediates) {
            $body['intermediates'] = array_map(
                static fn (RouteWaypoint $waypoint): array => $waypoint->toGoogleWaypoint(),
                $intermediates,
            );
        }

        if ($options->optimizeWaypointOrder && [] !== $intermediates) {
            $body['optimizeWaypointOrder'] = true;
        }

        if ($supportsTrafficAndModifiers) {
            if (RoutingPreference::TRAFFIC_UNAWARE !== $options->routingPreference) {
                $body['routingPreference'] = $options->routingPreference->value;
            }

            if (!$options->modifiers->isDefault()) {
                $body['routeModifiers'] = array_filter([
                    'avoidHighways' => $options->modifiers->avoidHighways ?: null,
                    'avoidTolls' => $options->modifiers->avoidTolls ?: null,
                    'avoidFerries' => $options->modifiers->avoidFerries ?: null,
                ], static fn (mixed $value): bool => null !== $value);
            }

            if ($options->showTollEstimates) {
                $body['extraComputations'] = ['TOLLS'];

                if (null !== $options->vehicleProfile) {
                    $body['routeModifiers'] ??= [];
                    $body['routeModifiers']['vehicleInfo'] = ['emissionType' => $options->vehicleProfile->emissionType->value];
                }
            }
        }

        if ($options->computeAlternativeRoutes) {
            $body['computeAlternativeRoutes'] = true;
        }

        if ($options->showFuelEfficientRoute && TravelMode::DRIVE === $travelMode) {
            $body['requestedReferenceRoutes'] = ['FUEL_EFFICIENT'];
        }

        try {
            $response = $this->httpClient->request('POST', '/directions/v2:computeRoutes', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ],
                'json' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Google Routes API transport error.', ['exception' => $exception]);

            throw new RoutingProviderUnavailableException('Unable to reach the routing provider.', previous: $exception);
        }

        if ($statusCode >= 400) {
            $this->logger->error('Google Routes API returned an error status.', ['status' => $statusCode, 'body' => $content]);

            throw new RoutingProviderUnavailableException(sprintf('Routing provider returned status %d.', $statusCode));
        }

        $decoded = json_decode($content, true);

        if (!\is_array($decoded)) {
            $this->logger->error('Google Routes API returned malformed JSON.', ['body' => $content]);

            throw new RoutingProviderUnavailableException('Routing provider returned an unreadable response.');
        }

        $routes = $decoded['routes'] ?? null;

        if (!\is_array($routes) || [] === $routes) {
            throw new RouteNotFoundException('No route found between the given locations.');
        }

        $results = [];
        $hasMultipleRoutes = \count($routes) > 1;

        foreach (array_values($routes) as $index => $route) {
            if (!\is_array($route)) {
                throw new RoutingProviderUnavailableException('Routing provider returned a malformed route.');
            }

            $results[] = $this->parseRouteResult($route, $travelMode, $index, $hasMultipleRoutes);
        }

        return new RouteComputation($results, $this->costClassifier->classify($options));
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
            maxIntermediateWaypoints: self::MAX_INTERMEDIATE_WAYPOINTS,
        );
    }

    /**
     * @param array<string, mixed> $route
     */
    private function parseRouteResult(array $route, TravelMode $travelMode, int $index, bool $hasMultipleRoutes): RouteResult
    {
        $distanceMeters = $route['distanceMeters'] ?? null;

        if (!\is_int($distanceMeters)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed distance.');
        }

        $legsData = $route['legs'] ?? null;

        if (!\is_array($legsData) || [] === $legsData) {
            throw new RoutingProviderUnavailableException('Routing provider returned no legs.');
        }

        $legs = array_values(array_map($this->parseRouteLeg(...), $legsData));

        return new RouteResult(
            distanceMeters: $distanceMeters,
            durationSeconds: $this->parseDurationSeconds($route['duration'] ?? null),
            points: $this->parseGeometry($route['polyline'] ?? null),
            legs: $legs,
            travelMode: $travelMode,
            routeLabel: $this->parseRouteLabel($route['routeLabels'] ?? null, $index, $hasMultipleRoutes),
            optimizedWaypointOrder: $this->parseOptimizedWaypointOrder($route['optimizedIntermediateWaypointIndex'] ?? null),
            tollEstimate: $this->parseTollEstimate($route['travelAdvisory'] ?? null),
        );
    }

    private function parseRouteLabel(mixed $routeLabels, int $index, bool $hasMultipleRoutes): ?string
    {
        if (!$hasMultipleRoutes) {
            return null;
        }

        if (\is_array($routeLabels) && \in_array('FUEL_EFFICIENT', $routeLabels, true)) {
            return 'FUEL_EFFICIENT';
        }

        return match ($index) {
            0 => 'FASTEST',
            default => 'ALTERNATE',
        };
    }

    /**
     * @return list<int>|null
     */
    private function parseOptimizedWaypointOrder(mixed $order): ?array
    {
        if (!\is_array($order) || [] === $order) {
            return null;
        }

        $indexes = [];

        foreach ($order as $value) {
            if (!\is_int($value)) {
                return null;
            }

            $indexes[] = $value;
        }

        return $indexes;
    }

    private function parseTollEstimate(mixed $travelAdvisory): ?RouteTollEstimate
    {
        if (!\is_array($travelAdvisory)) {
            return null;
        }

        $tollInfo = $travelAdvisory['tollInfo'] ?? null;
        $estimatedPrice = \is_array($tollInfo) ? ($tollInfo['estimatedPrice'] ?? null) : null;

        if (!\is_array($estimatedPrice) || !isset($estimatedPrice[0]) || !\is_array($estimatedPrice[0])) {
            return null;
        }

        $money = $estimatedPrice[0];
        $currencyCode = $money['currencyCode'] ?? null;
        $units = $money['units'] ?? 0;
        $nanos = $money['nanos'] ?? 0;

        if (!\is_string($currencyCode) || '' === $currencyCode) {
            return null;
        }

        $unitsFloat = is_numeric($units) ? (float) $units : 0.0;
        $nanosFloat = is_numeric($nanos) ? (float) $nanos : 0.0;

        return new RouteTollEstimate($currencyCode, $unitsFloat + $nanosFloat / 1_000_000_000);
    }

    private function parseRouteLeg(mixed $leg): RouteLeg
    {
        if (!\is_array($leg)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed leg.');
        }

        $distanceMeters = $leg['distanceMeters'] ?? null;

        if (!\is_int($distanceMeters)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed leg distance.');
        }

        return new RouteLeg(
            distanceMeters: $distanceMeters,
            durationSeconds: $this->parseDurationSeconds($leg['duration'] ?? null),
            startPoint: $this->parseLatLng($leg['startLocation'] ?? null),
            endPoint: $this->parseLatLng($leg['endLocation'] ?? null),
        );
    }

    private function parseDurationSeconds(mixed $duration): int
    {
        if (!\is_string($duration) || 1 !== preg_match('/^(\d+)(?:\.\d+)?s$/', $duration, $matches)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed duration.');
        }

        return (int) $matches[1];
    }

    private function parseLatLng(mixed $location): RoutePoint
    {
        if (!\is_array($location)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed location.');
        }

        $latLng = $location['latLng'] ?? null;

        if (!\is_array($latLng) || !isset($latLng['latitude'], $latLng['longitude'])
            || !is_numeric($latLng['latitude']) || !is_numeric($latLng['longitude'])
        ) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed latLng.');
        }

        return new RoutePoint((float) $latLng['latitude'], (float) $latLng['longitude']);
    }

    /**
     * @return list<RoutePoint>
     */
    private function parseGeometry(mixed $polyline): array
    {
        if (!\is_array($polyline)) {
            throw new RoutingProviderUnavailableException('Routing provider returned no polyline.');
        }

        $geoJson = $polyline['geoJsonLinestring'] ?? null;

        if (!\is_array($geoJson)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed polyline.');
        }

        $coordinates = $geoJson['coordinates'] ?? null;

        if (!\is_array($coordinates)) {
            throw new RoutingProviderUnavailableException('Routing provider returned malformed geometry.');
        }

        $points = [];

        foreach ($coordinates as $coordinate) {
            // GeoJSON coordinate order is [longitude, latitude].
            if (!\is_array($coordinate) || !isset($coordinate[0], $coordinate[1])
                || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])
            ) {
                throw new RoutingProviderUnavailableException('Routing provider returned a malformed coordinate.');
            }

            $points[] = new RoutePoint((float) $coordinate[1], (float) $coordinate[0]);
        }

        return $points;
    }
}
