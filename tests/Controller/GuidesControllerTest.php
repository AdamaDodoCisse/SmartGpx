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
        yield 'google maps to garmin (en)' => ['/guides/google-maps-to-garmin', 'guide-google-maps-to-garmin'];
        yield 'google maps to garmin (fr)' => ['/fr/guides/convertir-google-maps-en-garmin', 'guide-google-maps-to-garmin'];
        yield 'google maps to wahoo (en)' => ['/guides/google-maps-to-wahoo', 'guide-google-maps-to-wahoo'];
        yield 'google maps to wahoo (fr)' => ['/fr/guides/convertir-google-maps-en-wahoo', 'guide-google-maps-to-wahoo'];
        yield 'google maps to osmand (en)' => ['/guides/google-maps-to-osmand', 'guide-google-maps-to-osmand'];
        yield 'google maps to osmand (fr)' => ['/fr/guides/convertir-google-maps-en-osmand', 'guide-google-maps-to-osmand'];
    }

    #[DataProvider('deviceGuideRoutes')]
    public function testDeviceGuidePagesHaveCompleteSeoAndStructuredData(string $path, string $expectedId, string $otherGuideRoutePath): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // Titre/H1/meta description uniques : un seul <title>, une seule balise meta description.
        self::assertSame(1, substr_count($content, '<title>'));
        self::assertSame(1, substr_count($content, 'name="description"'));
        self::assertStringNotContainsString('noindex', $content);

        self::assertStringContainsString('rel="canonical"', $content);
        self::assertStringContainsString('id="convert-hero-root"', $content);
        self::assertStringContainsString('aria-label="Breadcrumb"', $content);
        self::assertStringContainsString('"@type": "BreadcrumbList"', $content);
        self::assertStringContainsString('"@type": "HowTo"', $content);
        self::assertStringContainsString('"@type": "FAQPage"', $content);

        self::assertStringContainsString('id="'.$expectedId.'"', $content);
        self::assertStringContainsString('href="/guides/google-maps-to-gpx"', $content);
        self::assertStringContainsString('href="'.$otherGuideRoutePath.'"', $content);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function deviceGuideRoutes(): iterable
    {
        yield 'garmin' => ['/guides/google-maps-to-garmin', 'guide-google-maps-to-garmin', '/guides/google-maps-to-wahoo'];
        yield 'wahoo' => ['/guides/google-maps-to-wahoo', 'guide-google-maps-to-wahoo', '/guides/google-maps-to-garmin'];
        yield 'osmand' => ['/guides/google-maps-to-osmand', 'guide-google-maps-to-osmand', '/guides/google-maps-to-garmin'];
    }
}
