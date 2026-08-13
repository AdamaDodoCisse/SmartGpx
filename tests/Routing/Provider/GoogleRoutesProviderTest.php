<?php

declare(strict_types=1);

namespace App\Tests\Routing\Provider;

use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Provider\GoogleRoutesProvider;
use App\Routing\ValueObject\Address;
use App\Routing\ValueObject\Coordinates;
use App\Routing\ValueObject\RouteLocation;
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
    public function testComputeRouteSendsTheCorrectWaypointShapeAndParsesTheResponse(
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

        $provider = new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger());

        $result = $provider->computeRoute($origin, $destination, [], TravelMode::DRIVE);

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

        self::assertSame(12345, $result->distanceMeters);
        self::assertSame(900, $result->durationSeconds);
        self::assertCount(3, $result->points);
        self::assertSame(48.8566, $result->points[0]->latitude);
        self::assertSame(2.3522, $result->points[0]->longitude);
        self::assertCount(1, $result->legs);
        self::assertSame(TravelMode::DRIVE, $result->travelMode);
    }

    public function testTransportErrorMapsToRoutingProviderUnavailable(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', ['error' => 'Connection timed out']),
            'https://routes.googleapis.com',
        );

        $provider = new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger());

        $this->expectException(RoutingProviderUnavailableException::class);
        $provider->computeRoute(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }

    public function testEmptyRoutesArrayMapsToRouteNotFound(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('{"routes": []}', ['http_code' => 200]),
            'https://routes.googleapis.com',
        );

        $provider = new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger());

        $this->expectException(RouteNotFoundException::class);
        $provider->computeRoute(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }

    public function testErrorStatusMapsToRoutingProviderUnavailableWithoutLeakingDetail(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('{"error": {"message": "API key not valid. secret-value-should-not-leak"}}', ['http_code' => 403]),
            'https://routes.googleapis.com',
        );

        $provider = new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger());

        try {
            $provider->computeRoute(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
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

        $provider = new GoogleRoutesProvider($httpClient, 'test-api-key', new NullLogger());

        $this->expectException(RoutingProviderUnavailableException::class);
        $provider->computeRoute(Address::fromString('A'), Address::fromString('B'), [], TravelMode::DRIVE);
    }
}
