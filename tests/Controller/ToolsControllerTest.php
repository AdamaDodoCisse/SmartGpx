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
    }
}
