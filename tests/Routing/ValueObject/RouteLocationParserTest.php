<?php

declare(strict_types=1);

namespace App\Tests\Routing\ValueObject;

use App\Routing\ValueObject\Address;
use App\Routing\ValueObject\Coordinates;
use App\Routing\ValueObject\RouteLocationParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Régression critique du brief produit : une chaîne de coordonnées ne doit jamais être envoyée
 * au fournisseur de routing comme une adresse littérale.
 */
final class RouteLocationParserTest extends TestCase
{
    private RouteLocationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RouteLocationParser();
    }

    #[DataProvider('coordinateStrings')]
    public function testKnownCoordinateStringsAreParsedAsCoordinates(string $raw, float $expectedLat, float $expectedLng): void
    {
        $location = $this->parser->parse($raw);

        self::assertInstanceOf(Coordinates::class, $location);
        self::assertSame($expectedLat, $location->latitude);
        self::assertSame($expectedLng, $location->longitude);

        // La règle de non-régression : jamais de clé "address" pour des coordonnées reconnues.
        $waypoint = $location->toGoogleWaypoint();
        self::assertArrayNotHasKey('address', $waypoint);
        self::assertArrayHasKey('location', $waypoint);
        self::assertSame($expectedLat, $waypoint['location']['latLng']['latitude']);
        self::assertSame($expectedLng, $waypoint['location']['latLng']['longitude']);
    }

    /**
     * @return iterable<string, array{string, float, float}>
     */
    public static function coordinateStrings(): iterable
    {
        // Cas exact mentionné explicitement par le brief produit.
        yield 'brief exact case' => ['49.051624,2.0093594', 49.051624, 2.0093594];
        yield 'Paris' => ['48.8566,2.3522', 48.8566, 2.3522];
        yield 'Sydney (négatif/négatif)' => ['-33.8688,151.2093', -33.8688, 151.2093];
    }

    #[DataProvider('addressLikeStrings')]
    public function testNonCoordinateStringsFallBackToAddress(string $raw): void
    {
        $location = $this->parser->parse($raw);

        self::assertInstanceOf(Address::class, $location);

        $waypoint = $location->toGoogleWaypoint();
        self::assertArrayHasKey('address', $waypoint);
        self::assertArrayNotHasKey('location', $waypoint);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressLikeStrings(): iterable
    {
        yield 'real address' => ['Cergy, France'];
        yield 'malformed - letters' => ['abc,def'];
        yield 'malformed - single number' => ['48.8566'];
        yield 'malformed - three numbers' => ['48.8566,2.3522,10'];
        yield 'out of range - both' => ['200,200'];
        yield 'out of range - longitude' => ['48.8566,200'];
        yield 'out of range - latitude' => ['-200,2.3522'];
        yield 'out of range - latitude just above 90' => ['90.0001,2.3522'];
    }

    public function testExactBriefCoordinateStringNeverBecomesAnAddress(): void
    {
        $location = $this->parser->parse('49.051624,2.0093594');

        self::assertNotInstanceOf(Address::class, $location);
    }
}
