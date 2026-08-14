<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ToolsControllerTest extends WebTestCase
{
    #[DataProvider('routes')]
    public function testItRendersSuccessfullyAndMountsTheExpectedIsland(string $path, string $expectedRootId): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('id="'.$expectedRootId.'-root"', (string) $client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'gpx viewer (en)' => ['/gpx-viewer', 'gpx-viewer'];
        yield 'gpx viewer (fr)' => ['/fr/visionneuse-gpx', 'gpx-viewer'];
        yield 'gpx to google maps (en)' => ['/tools/gpx-to-google-maps', 'gpx-to-google-maps'];
        yield 'gpx to google maps (fr)' => ['/fr/outils/gpx-vers-google-maps', 'gpx-to-google-maps'];
        yield 'gpx simplify (en)' => ['/tools/gpx-simplify', 'gpx-simplify'];
        yield 'gpx simplify (fr)' => ['/fr/outils/simplifier-gpx', 'gpx-simplify'];
        yield 'gpx merge (en)' => ['/tools/gpx-merge', 'gpx-merge'];
        yield 'gpx merge (fr)' => ['/fr/outils/fusionner-gpx', 'gpx-merge'];
        yield 'kml to gpx (en)' => ['/tools/kml-to-gpx', 'kml-to-gpx'];
        yield 'kml to gpx (fr)' => ['/fr/outils/kml-vers-gpx', 'kml-to-gpx'];
        yield 'gpx to kml (en)' => ['/tools/gpx-to-kml', 'gpx-to-kml'];
        yield 'gpx to kml (fr)' => ['/fr/outils/gpx-vers-kml', 'gpx-to-kml'];
        yield 'kmz to gpx (en)' => ['/tools/kmz-to-gpx', 'kmz-to-gpx'];
        yield 'kmz to gpx (fr)' => ['/fr/outils/kmz-vers-gpx', 'kmz-to-gpx'];
        yield 'tcx to gpx (en)' => ['/tools/tcx-to-gpx', 'tcx-to-gpx'];
        yield 'tcx to gpx (fr)' => ['/fr/outils/tcx-vers-gpx', 'tcx-to-gpx'];
        yield 'gpx to tcx (en)' => ['/tools/gpx-to-tcx', 'gpx-to-tcx'];
        yield 'gpx to tcx (fr)' => ['/fr/outils/gpx-vers-tcx', 'gpx-to-tcx'];
        yield 'fit to gpx (en)' => ['/tools/fit-to-gpx', 'fit-to-gpx'];
        yield 'fit to gpx (fr)' => ['/fr/outils/fit-vers-gpx', 'fit-to-gpx'];
        yield 'gpx to fit (en)' => ['/tools/gpx-to-fit', 'gpx-to-fit'];
        yield 'gpx to fit (fr)' => ['/fr/outils/gpx-vers-fit', 'gpx-to-fit'];
        yield 'geojson to gpx (en)' => ['/tools/geojson-to-gpx', 'geojson-to-gpx'];
        yield 'geojson to gpx (fr)' => ['/fr/outils/geojson-vers-gpx', 'geojson-to-gpx'];
        yield 'gpx to geojson (en)' => ['/tools/gpx-to-geojson', 'gpx-to-geojson'];
        yield 'gpx to geojson (fr)' => ['/fr/outils/gpx-vers-geojson', 'gpx-to-geojson'];
    }
}
