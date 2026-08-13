<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Parser;

use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsShortLinkResolver;
use App\Conversion\Parser\GoogleMapsUrlParser;
use App\Routing\Enum\TravelMode;
use App\Routing\ValueObject\Address;
use App\Routing\ValueObject\Coordinates;
use App\Routing\ValueObject\RouteLocationParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class GoogleMapsUrlParserTest extends TestCase
{
    private GoogleMapsUrlParser $parser;

    protected function setUp(): void
    {
        // Un client qui échoue si jamais sollicité : aucun de ces tests ne doit résoudre un lien
        // court (ce sont tous des hôtes google.com directs).
        $neverCalledClient = new MockHttpClient(static function (): never {
            throw new \LogicException('The short-link HTTP client should never be called for a direct google.com URL.');
        });

        $this->parser = new GoogleMapsUrlParser(
            new RouteLocationParser(),
            new GoogleMapsShortLinkResolver($neverCalledClient),
        );
    }

    public function testEmptyUrlIsInvalid(): void
    {
        $this->expectException(InvalidGoogleMapsUrlException::class);
        $this->parser->parse('');
    }

    public function testNonGoogleHostIsUnsupported(): void
    {
        $this->expectException(UnsupportedGoogleMapsUrlException::class);
        $this->parser->parse('https://example.com/maps/dir/Cergy/Paris');
    }

    public function testViewOnlyLinkIsUnsupported(): void
    {
        $this->expectException(UnsupportedGoogleMapsUrlException::class);
        $this->parser->parse('https://www.google.com/maps/place/Paris/@48.8566,2.3522,12z');
    }

    public function testSingleEndpointPathSegmentLinkIsUnsupported(): void
    {
        $this->expectException(UnsupportedGoogleMapsUrlException::class);
        $this->parser->parse('https://www.google.com/maps/dir/Paris,+France/@48.8566,2.3522,12z');
    }

    public function testDocumentedApiFormatWithAddressesAndExplicitTravelMode(): void
    {
        $url = 'https://www.google.com/maps/dir/?api=1'
            .'&origin=Cergy%2C+France'
            .'&destination=Paris%2C+France'
            .'&travelmode=walking';

        $result = $this->parser->parse($url);

        self::assertInstanceOf(Address::class, $result->origin);
        self::assertSame('Cergy, France', $result->origin->label());
        self::assertInstanceOf(Address::class, $result->destination);
        self::assertSame(TravelMode::WALK, $result->travelMode);
        self::assertFalse($result->travelModeInferred);
        self::assertSame([], $result->intermediates);
    }

    public function testDocumentedApiFormatWithCoordinatesAndWaypoints(): void
    {
        $url = 'https://www.google.com/maps/dir/?api=1'
            .'&origin=49.051624%2C2.0093594'
            .'&destination=48.8566%2C2.3522'
            .'&waypoints=Pontoise%2C+France'
            .'&travelmode=driving';

        $result = $this->parser->parse($url);

        self::assertInstanceOf(Coordinates::class, $result->origin);
        self::assertSame(49.051624, $result->origin->latitude);
        self::assertInstanceOf(Coordinates::class, $result->destination);
        self::assertCount(1, $result->intermediates);
        self::assertInstanceOf(Address::class, $result->intermediates[0]);
        self::assertSame(TravelMode::DRIVE, $result->travelMode);
        self::assertFalse($result->travelModeInferred);
    }

    public function testDocumentedApiFormatWithoutTravelmodeDefaultsToDriveAndFlagsInference(): void
    {
        $url = 'https://www.google.com/maps/dir/?api=1&origin=Cergy&destination=Paris';

        $result = $this->parser->parse($url);

        self::assertSame(TravelMode::DRIVE, $result->travelMode);
        self::assertTrue($result->travelModeInferred);
    }

    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function pathSegmentUrls(): iterable
    {
        yield 'two segments' => [
            'https://www.google.com/maps/dir/Cergy,+France/Paris,+France/@48.9,2.2,10z/data=!3e0',
            'Cergy, France',
            'Paris, France',
            [],
        ];
        yield 'three segments with intermediate' => [
            'https://www.google.com/maps/dir/Cergy,+France/Pontoise,+France/Paris,+France/@48.9,2.2,10z',
            'Cergy, France',
            'Paris, France',
            ['Pontoise, France'],
        ];
    }

    /**
     * @param list<string> $expectedIntermediateLabels
     */
    #[DataProvider('pathSegmentUrls')]
    public function testPathSegmentFormatParsesLocationsAndInfersTravelMode(
        string $url,
        string $expectedOrigin,
        string $expectedDestination,
        array $expectedIntermediateLabels,
    ): void {
        $result = $this->parser->parse($url);

        self::assertSame($expectedOrigin, $result->origin->label());
        self::assertSame($expectedDestination, $result->destination->label());
        self::assertSame(
            $expectedIntermediateLabels,
            array_map(static fn ($location) => $location->label(), $result->intermediates),
        );
        self::assertSame(TravelMode::DRIVE, $result->travelMode);
        self::assertTrue($result->travelModeInferred, 'Travel mode must be flagged as inferred for path-segment links.');
    }

    public function testPathSegmentFormatWithCoordinateEndpoints(): void
    {
        $url = 'https://www.google.com/maps/dir/49.051624,2.0093594/48.8566,2.3522/@48.9,2.2,10z';

        $result = $this->parser->parse($url);

        self::assertInstanceOf(Coordinates::class, $result->origin);
        self::assertInstanceOf(Coordinates::class, $result->destination);
    }
}
