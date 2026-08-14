<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GuidesControllerTest extends WebTestCase
{
    #[DataProvider('routes')]
    public function testItRendersSuccessfullyAndContainsTheExpectedMarker(string $path, string $expectedId): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('id="'.$expectedId.'"', (string) $client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'guides index (en)' => ['/guides', 'guides-index'];
        yield 'guides index (fr)' => ['/fr/guides', 'guides-index'];
        yield 'gpx vs kml (en)' => ['/guides/gpx-vs-kml', 'guide-gpx-vs-kml'];
        yield 'gpx vs kml (fr)' => ['/fr/guides/gpx-ou-kml', 'guide-gpx-vs-kml'];
        yield 'gpx vs tcx (en)' => ['/guides/gpx-vs-tcx', 'guide-gpx-vs-tcx'];
        yield 'gpx vs tcx (fr)' => ['/fr/guides/gpx-ou-tcx', 'guide-gpx-vs-tcx'];
        yield 'gpx vs fit (en)' => ['/guides/gpx-vs-fit', 'guide-gpx-vs-fit'];
        yield 'gpx vs fit (fr)' => ['/fr/guides/gpx-ou-fit', 'guide-gpx-vs-fit'];
        yield 'gpx vs geojson (en)' => ['/guides/gpx-vs-geojson', 'guide-gpx-vs-geojson'];
        yield 'gpx vs geojson (fr)' => ['/fr/guides/gpx-ou-geojson', 'guide-gpx-vs-geojson'];
        yield 'google maps to gpx (en)' => ['/guides/google-maps-to-gpx', 'guide-google-maps-to-gpx'];
        yield 'google maps to gpx (fr)' => ['/fr/guides/convertir-google-maps-en-gpx', 'guide-google-maps-to-gpx'];
        yield 'what is kmz (en)' => ['/guides/what-is-kmz', 'guide-kmz'];
        yield 'what is kmz (fr)' => ['/fr/guides/fichier-kmz', 'guide-kmz'];
        yield 'simplify track (en)' => ['/guides/simplify-gps-track', 'guide-simplify-track'];
        yield 'simplify track (fr)' => ['/fr/guides/simplifier-une-trace-gps', 'guide-simplify-track'];
        yield 'merge tracks (en)' => ['/guides/merge-gpx-tracks', 'guide-merge-tracks'];
        yield 'merge tracks (fr)' => ['/fr/guides/fusionner-plusieurs-gpx', 'guide-merge-tracks'];
    }
}
