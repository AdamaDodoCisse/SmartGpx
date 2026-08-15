<?php

declare(strict_types=1);

namespace App\Tests\Routing\Provider;

use App\Routing\Enum\RoutingPreference;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Exception\TooManyWaypointsException;
use App\Routing\Provider\GoogleRoutesProvider;
use App\Routing\Service\RouteOptionsCostClassifier;
use App\Routing\ValueObject\Address;
use App\Routing\ValueObject\Coordinates;
use App\Routing\ValueObject\RouteLocation;
use App\Routing\ValueObject\RouteModifiers;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\RouteWaypoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleRoutesProviderTest extends TestCase
{
    private const string FIXTURE_RESPONSE = <<<'JSON'
        {
            "routes": [
                {
                    "distanceMeters": 12345,
                    "duration": "900s",
                    "polyline": {
                        "geoJsonLinestring": {
                            "type": "LineString",
                            "coordinates": [[2.3522, 48.8566], [2.4, 48.9], [2.0093594, 49.051624]]
                        }
                    },
                    "legs": [
                        {
                            "distanceMeters": 12345,
                            "duration": "900s",
                            "startLocation": {"latLng": {"latitude": 48.8566, "longitude": 2.3522}},
                            "endLocation": {"latLng": {"latitude": 49.051624, "longitude": 2.0093594}}
                        }
                    ]
                }
            ]
        }
        JSON;

    private const string MULTI_ROUTE_RESPONSE = <<<'JSON'
        {
            "routes": [
                {
                    "distanceMeters": 12345,
                    "duration": "900s",
                    "polyline": {"geoJsonLinestring": {"type": "LineString", "coordinates": [[2.3522, 48.8566], [2.0093594, 49.051624]]}},
                    "legs": [{"distanceMeters": 12345, "duration": "900s", "startLocation": {"latLng": {"latitude": 48.8566, "longitude": 2.3522}}, "endLocation": {"latLng": {"latitude": 49.051624, "longitude": 2.0093594}}}]
                },
                {
                    "distanceMeters": 13000,
                    "duration": "950s",
                    "routeLabels": ["FUEL_EFFICIENT"],
                    "polyline": {"geoJsonLinestring": {"type": "LineString", "coordinates": [[2.3522, 48.8566], [2.0093594, 49.051624]]}},
                    "legs": [{"distanceMeters": 13000, "duration": "950s", "startLocation": {"latLng": {"latitude": 48.8566, "longitude": 2.3522}}, "endLocation": {"latLng": {"latitude": 49.051624, "longitude": 2.0093594}}}],
                    "travelAdvisory": {"tollInfo": {"estimatedPrice": [{"currencyCode": "EUR", "units": "4", "nanos": 500000000}]}}
                }
            ]
        }
        JSON;

    /**
     * @return iterable<string, array{RouteLocation, RouteLocation}>
     */
    public static function locationCombinations(): iterable
    {
        yield 'address to address' => [Address::fromString('Cergy, France'), Address::fromString('Paris, France')];
        yield 'coordinates to address' => [Coordinates::fromValues(49.051624, 2.0093594), Address::fromString('Paris, France')];
        yield 'address to coordinates' => [Address::fromString('Cergy, France'), Coordinates::fromValues(48.8566, 2.3522)];
        yield 'coordinates to coordinates' => [Coordinates::fromValues(49.051624, 2.0093594), Coordinates::fromValues(48.8566, 2.3522)];
    }

    #[DataProvider('locationCombinations')]
    public function testComputeRoutesSendsTheCorrectWaypointShapeAndParsesTheResponse(
        RouteLocation $origin,
        RouteLocation $destination,
    ): void {
        $capturedBody = null;
        $capturedHeaders = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody, &$capturedHeaders): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);
                $capturedHeaders = $options['headers'];

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $computation = $provider->computeRoutes($origin, $destination, [], TravelMode::DRIVE);

        self::assertIsArray($capturedBody);

        // Règle de non-régression : jamais de clé "address" pour un waypoint de type Coordinates.
        if ($origin instanceof Coordinates) {
            self::assertArrayNotHasKey('address', $capturedBody['origin']);
            self::assertArrayHasKey('location', $capturedBody['origin']);
        } else {
            self::assertArrayHasKey('address', $capturedBody['origin']);
        }

        if ($destination instanceof Coordinates) {
            self::assertArrayNotHasKey('address', $capturedBody['destination']);
            self::assertArrayHasKey('location', $capturedBody['destination']);
        } else {
            self::assertArrayHasKey('address', $capturedBody['destination']);
        }

        self::assertIsArray($capturedHeaders);
        self::assertContains('X-Goog-Api-Key: test-api-key', $capturedHeaders);

        $result = $computation->primary();
        self::assertSame(12345, $result->distanceMeters);
        self::assertSame(900, $result->durationSeconds);
        self::assertCount(3, $result->points);
        self::assertSame(48.8566, $result->points[0]->latitude);
        self::assertSame(2.3522, $result->points[0]->longitude);
        self::assertCount(1, $result->legs);
        self::assertSame(TravelMode::DRIVE, $result->travelMode);
        self::assertNull($result->routeLabel, 'A single route (no alternatives requested) has no label.');
    }

    public function testViaWaypointIsMarkedInTheRequestBody(): void
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $stop = new RouteWaypoint(Address::fromString('Orléans, France'), WaypointType::STOP, 0);
        $via = new RouteWaypoint(Address::fromString('Tours, France'), WaypointType::VIA, 1);

        $provider->computeRoutes(Address::fromString('Paris'), Address::fromString('Lyon'), [$stop, $via], TravelMode::DRIVE);

        self::assertIsArray($capturedBody);
        self::assertArrayNotHasKey('via', $capturedBody['intermediates'][0]);
        self::assertTrue($capturedBody['intermediates'][1]['via']);
    }

    public function testAvoidModifiersAreSentOnlyWhenNotDefault(): void
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);
        $options = new RouteOptions(modifiers: new RouteModifiers(avoidHighways: true, avoidTolls: true, avoidFerries: false));

        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE, $options);

        self::assertIsArray($capturedBody);
        self::assertTrue($capturedBody['routeModifiers']['avoidHighways']);
        self::assertTrue($capturedBody['routeModifiers']['avoidTolls']);
        self::assertArrayNotHasKey('avoidFerries', $capturedBody['routeModifiers']);
    }

    public function testAvoidModifiersAreNeverSentForWalkingMode(): void
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);
        $options = new RouteOptions(
            routingPreference: RoutingPreference::TRAFFIC_AWARE,
            modifiers: new RouteModifiers(avoidHighways: true),
        );

        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::WALK, $options);

        self::assertIsArray($capturedBody);
        self::assertArrayNotHasKey('routeModifiers', $capturedBody, 'WALK does not support routeModifiers on the real Google Routes API.');
        self::assertArrayNotHasKey('routingPreference', $capturedBody);
    }

    #[DataProvider('routingPreferenceValues')]
    public function testRoutingPreferenceIsForwarded(RoutingPreference $preference): void
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);
        $options = new RouteOptions(routingPreference: $preference);

        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE, $options);

        self::assertIsArray($capturedBody);

        if (RoutingPreference::TRAFFIC_UNAWARE === $preference) {
            self::assertArrayNotHasKey('routingPreference', $capturedBody);
        } else {
            self::assertSame($preference->value, $capturedBody['routingPreference']);
        }
    }

    /**
     * @return iterable<string, array{RoutingPreference}>
     */
    public static function routingPreferenceValues(): iterable
    {
        yield 'unaware' => [RoutingPreference::TRAFFIC_UNAWARE];
        yield 'aware' => [RoutingPreference::TRAFFIC_AWARE];
        yield 'aware optimal' => [RoutingPreference::TRAFFIC_AWARE_OPTIMAL];
    }

    public function testOptimizeWaypointOrderIsOnlySentWithIntermediates(): void
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                $capturedBody = json_decode((string) $options['body'], true);

                return new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]);
            },
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);
        $options = new RouteOptions(optimizeWaypointOrder: true);

        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE, $options);

        self::assertIsArray($capturedBody);
        self::assertArrayNotHasKey('optimizeWaypointOrder', $capturedBody, 'No intermediates: nothing to optimize.');
    }

    public function testAlternativesAndFuelEfficientRouteAreParsedWithLabelsAndTollEstimate(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(self::MULTI_ROUTE_RESPONSE, ['http_code' => 200]),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);
        $options = new RouteOptions(computeAlternativeRoutes: true, showFuelEfficientRoute: true);

        $computation = $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE, $options);

        self::assertTrue($computation->hasAlternatives());
        self::assertCount(2, $computation->routes);
        self::assertSame('FASTEST', $computation->routes[0]->routeLabel);
        self::assertSame('FUEL_EFFICIENT', $computation->routes[1]->routeLabel);
        self::assertNotNull($computation->routes[1]->tollEstimate);
        self::assertSame('EUR', $computation->routes[1]->tollEstimate->currencyCode);
        self::assertEqualsWithDelta(4.5, $computation->routes[1]->tollEstimate->amount, 0.0001);
    }

    public function testTooManyIntermediateWaypointsThrows(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(self::FIXTURE_RESPONSE, ['http_code' => 200]),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $intermediates = array_map(
            static fn (int $i): RouteWaypoint => new RouteWaypoint(Address::fromString('Stop '.$i), WaypointType::STOP, $i),
            range(0, $provider->capabilities()->maxIntermediateWaypoints),
        );

        $this->expectException(TooManyWaypointsException::class);
        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), $intermediates, TravelMode::DRIVE);
    }

    public function testCapabilitiesDeclareGooglesRealFeatureSet(): void
    {
        $provider = self::createProvider(new MockHttpClient());
        $capabilities = $provider->capabilities();

        self::assertTrue($capabilities->avoidHighways);
        self::assertTrue($capabilities->avoidTolls);
        self::assertTrue($capabilities->avoidFerries);
        self::assertTrue($capabilities->trafficAware);
        self::assertTrue($capabilities->trafficAwareOptimal);
        self::assertTrue($capabilities->waypointOptimization);
        self::assertTrue($capabilities->alternativeRoutes);
        self::assertTrue($capabilities->fuelEfficientRoute);
        self::assertTrue($capabilities->tollEstimation);
        self::assertSame(25, $capabilities->maxIntermediateWaypoints);
        self::assertContains(TravelMode::TWO_WHEELER, $capabilities->supportedTravelModes);
    }

    public function testTransportErrorMapsToRoutingProviderUnavailable(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', ['error' => 'Connection timed out']),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $this->expectException(RoutingProviderUnavailableException::class);
        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }

    public function testEmptyRoutesArrayMapsToRouteNotFound(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('{"routes": []}', ['http_code' => 200]),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $this->expectException(RouteNotFoundException::class);
        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }

    public function testErrorStatusMapsToRoutingProviderUnavailableWithoutLeakingDetail(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('{"error": {"message": "API key not valid. secret-value-should-not-leak"}}', ['http_code' => 403]),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        try {
            $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
            self::fail('Expected RoutingProviderUnavailableException was not thrown.');
        } catch (RoutingProviderUnavailableException $exception) {
            self::assertStringNotContainsString('secret-value-should-not-leak', $exception->getMessage());
            self::assertStringNotContainsString('test-api-key', $exception->getMessage());
        }
    }

    public function testMalformedJsonMapsToRoutingProviderUnavailable(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('not json at all', ['http_code' => 200]),
            'https://routes.googleapis.com',
        );

        $provider = self::createProvider($httpClient);

        $this->expectException(RoutingProviderUnavailableException::class);
        $provider->computeRoutes(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }

    private static function createProvider(MockHttpClient $httpClient): GoogleRoutesProvider
    {
        return new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger(), new RouteOptionsCostClassifier());
    }
}
