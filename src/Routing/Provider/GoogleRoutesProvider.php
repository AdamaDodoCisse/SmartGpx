<?php

declare(strict_types=1);

namespace App\Routing\Provider;

use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Result\RouteLeg;
use App\Routing\Result\RoutePoint;
use App\Routing\Result\RouteResult;
use App\Routing\ValueObject\RouteLocation;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation Google Routes API v2 (computeRoutes) de RoutingProviderInterface.
 * Aucun type ni message d'erreur spécifique à Google ne doit fuiter hors de cette classe —
 * voir documentation/decisions/ADR-001-routing-provider.md.
 */
final class GoogleRoutesProvider implements RoutingProviderInterface
{
    private const string FIELD_MASK = 'routes.distanceMeters,routes.duration,routes.polyline.geoJsonLinestring,'
        .'routes.legs.distanceMeters,routes.legs.duration,routes.legs.startLocation,routes.legs.endLocation';

    public function __construct(
        #[Autowire(service: 'google.routes.client')]
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'string:GOOGLE_ROUTES_API_KEY')]
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function computeRoute(
        RouteLocation $origin,
        RouteLocation $destination,
        array $intermediates,
        TravelMode $travelMode,
    ): RouteResult {
        $body = [
            'origin' => $origin->toGoogleWaypoint(),
            'destination' => $destination->toGoogleWaypoint(),
            'travelMode' => $travelMode->value,
            'polylineEncoding' => 'GEO_JSON_LINESTRING',
        ];

        if ([] !== $intermediates) {
            $body['intermediates'] = array_map(
                static fn (RouteLocation $location): array => $location->toGoogleWaypoint(),
                $intermediates,
            );
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

        $route = $routes[0];

        if (!\is_array($route)) {
            throw new RoutingProviderUnavailableException('Routing provider returned a malformed route.');
        }

        return $this->parseRouteResult($route, $travelMode);
    }

    /**
     * @param array<string, mixed> $route
     */
    private function parseRouteResult(array $route, TravelMode $travelMode): RouteResult
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
        );
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
