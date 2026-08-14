<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitemapControllerTest extends WebTestCase
{
    public function testSitemapContainsEveryPublicRouteInBothLocales(): void
    {
        $client = static::createClient();

        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/xml', (string) $client->getResponse()->headers->get('Content-Type'));

        $content = (string) $client->getResponse()->getContent();
        self::assertSame(54, substr_count($content, '<loc>'));
        self::assertStringContainsString('<loc>http://localhost/guides/what-is-kmz</loc>', $content);
        self::assertStringContainsString('<loc>http://localhost/fr/guides/fichier-kmz</loc>', $content);
        self::assertStringContainsString('<loc>http://localhost/tools/kmz-to-gpx</loc>', $content);
        self::assertStringContainsString('<loc>http://localhost/fr/</loc>', $content);
        self::assertStringContainsString('<loc>http://localhost/contact</loc>', $content);
        self::assertStringContainsString('<loc>http://localhost/fr/contact</loc>', $content);
    }

    public function testRobotsTxtPointsAtTheSitemap(): void
    {
        $client = static::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/plain', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Disallow: /account/', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Disallow: /admin', (string) $client->getResponse()->getContent());
    }
}
