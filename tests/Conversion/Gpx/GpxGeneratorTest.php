<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Gpx;

use App\Conversion\Gpx\GpxGenerator;
use App\Conversion\Gpx\GpxRouteData;
use App\Conversion\Gpx\GpxRouteOptionsMetadata;
use App\Conversion\Gpx\GpxTrackPoint;
use App\Conversion\Gpx\GpxWaypoint;
use PHPUnit\Framework\TestCase;

final class GpxGeneratorTest extends TestCase
{
    private const string GPX_NAMESPACE = 'http://www.topografix.com/GPX/1/1';

    public function testGeneratesAValidGpx11Document(): void
    {
        $route = new GpxRouteData(
            routeName: 'Cergy to Paris',
            waypoints: [
                new GpxWaypoint(49.051624, 2.0093594, 'Cergy, France', 'origin'),
                new GpxWaypoint(48.9, 2.4, 'Pontoise, France', 'stop'),
                new GpxWaypoint(48.8566, 2.3522, 'Paris, France', 'destination'),
            ],
            trackPoints: [
                new GpxTrackPoint(49.051624, 2.0093594),
                new GpxTrackPoint(48.95, 2.3),
                new GpxTrackPoint(48.9, 2.4),
                new GpxTrackPoint(48.8566, 2.3522),
            ],
        );

        $xml = (new GpxGenerator())->generate($route);

        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml);
        self::assertTrue($loaded, 'The generated GPX must be well-formed XML.');

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('gpx', self::GPX_NAMESPACE);

        $gpxElements = $xpath->query('/gpx:gpx');
        self::assertNotFalse($gpxElements);
        self::assertCount(1, $gpxElements);
        $root = $gpxElements->item(0);
        self::assertInstanceOf(\DOMElement::class, $root);
        self::assertSame('1.1', $root->getAttribute('version'));

        $waypoints = $xpath->query('/gpx:gpx/gpx:wpt');
        self::assertNotFalse($waypoints);
        self::assertCount(3, $waypoints);

        $trackPoints = $xpath->query('/gpx:gpx/gpx:trk/gpx:trkseg/gpx:trkpt');
        self::assertNotFalse($trackPoints);
        self::assertCount(4, $trackPoints);

        $firstTrackPoint = $trackPoints->item(0);
        self::assertInstanceOf(\DOMElement::class, $firstTrackPoint);
        self::assertSame('49.051624', $firstTrackPoint->getAttribute('lat'));
        self::assertSame('2.0093594', $firstTrackPoint->getAttribute('lon'));

        $elevations = $xpath->query('//gpx:ele');
        self::assertNotFalse($elevations);
        self::assertCount(0, $elevations, 'GPX 1.1 output must not contain <ele> (not supported in Phase 2).');

        $firstWaypointName = $xpath->query('/gpx:gpx/gpx:wpt[1]/gpx:name');
        self::assertNotFalse($firstWaypointName);
        $nameNode = $firstWaypointName->item(0);
        self::assertInstanceOf(\DOMElement::class, $nameNode);
        self::assertSame('Cergy, France', $nameNode->textContent);
    }

    public function testGeneratesValidGpxWithNoWaypoints(): void
    {
        $route = new GpxRouteData(
            routeName: 'Empty waypoints',
            waypoints: [],
            trackPoints: [new GpxTrackPoint(0.0, 0.0)],
        );

        $xml = (new GpxGenerator())->generate($route);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
    }

    public function testOmitsExtensionsBlockWhenNoRouteOptionsMetadataIsGiven(): void
    {
        $route = new GpxRouteData(
            routeName: 'No metadata',
            waypoints: [],
            trackPoints: [new GpxTrackPoint(0.0, 0.0)],
        );

        $xml = (new GpxGenerator())->generate($route);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('gpx', self::GPX_NAMESPACE);

        $extensions = $xpath->query('/gpx:gpx/gpx:metadata/gpx:extensions');
        self::assertNotFalse($extensions);
        self::assertCount(0, $extensions, 'No <extensions> block should appear when routeOptions metadata is absent.');
    }

    public function testIncludesRouteOptionsExtensionsBlockWhenMetadataIsGiven(): void
    {
        $route = new GpxRouteData(
            routeName: 'Cergy to Paris',
            waypoints: [],
            trackPoints: [new GpxTrackPoint(0.0, 0.0)],
            routeOptions: new GpxRouteOptionsMetadata(
                travelMode: 'DRIVE',
                avoidHighways: true,
                avoidTolls: false,
                avoidFerries: false,
                routingPreference: 'TRAFFIC_AWARE',
                costTier: 'STANDARD',
            ),
        );

        $xml = (new GpxGenerator())->generate($route);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml), 'The document must stay well-formed, valid GPX 1.1 with the extensions block present.');
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('gpx', self::GPX_NAMESPACE);
        $xpath->registerNamespace('smartgpx', 'https://smartgpx.app/gpx/extensions/1');

        $extensions = $xpath->query('/gpx:gpx/gpx:metadata/gpx:extensions/smartgpx:routeOptions');
        self::assertNotFalse($extensions);
        self::assertCount(1, $extensions);

        $node = $extensions->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        self::assertSame('DRIVE', $node->getAttribute('travelMode'));
        self::assertSame('true', $node->getAttribute('avoidHighways'));
        self::assertSame('false', $node->getAttribute('avoidTolls'));
        self::assertSame('TRAFFIC_AWARE', $node->getAttribute('routingPreference'));
        self::assertSame('STANDARD', $node->getAttribute('costTier'));
    }
}
