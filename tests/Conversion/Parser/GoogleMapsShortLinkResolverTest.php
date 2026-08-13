<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Parser;

use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Parser\GoogleMapsShortLinkResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleMapsShortLinkResolverTest extends TestCase
{
    public function testIsShortLinkRecognizesKnownHosts(): void
    {
        $resolver = new GoogleMapsShortLinkResolver(new MockHttpClient());

        self::assertTrue($resolver->isShortLink('https://maps.app.goo.gl/abc123'));
        self::assertTrue($resolver->isShortLink('https://goo.gl/maps/abc123'));
        self::assertFalse($resolver->isShortLink('https://www.google.com/maps/dir/A/B'));
        self::assertFalse($resolver->isShortLink('https://example.com/maps/dir/A/B'));
    }

    public function testResolveReturnsTheResponsesEffectiveUrl(): void
    {
        // MockHttpClient ne simule pas réellement l'enchaînement de redirections HTTP (cela
        // relève du transport réel, cURL/natif — voir le smoke test manuel dans
        // documentation/technique/google-maps-to-gpx.md) : ce test vérifie seulement que
        // resolve() renvoie fidèlement getInfo('url') de la réponse reçue, quelle qu'elle soit.
        $requestedUrl = 'https://maps.app.goo.gl/abc123';

        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', ['http_code' => 200]),
        );

        $resolver = new GoogleMapsShortLinkResolver($httpClient);

        self::assertSame($requestedUrl, $resolver->resolve($requestedUrl));
    }

    public function testUnreachableShortLinkThrowsUnsupportedException(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', ['error' => 'Could not resolve host']),
        );

        $resolver = new GoogleMapsShortLinkResolver($httpClient);

        $this->expectException(UnsupportedGoogleMapsUrlException::class);
        $resolver->resolve('https://maps.app.goo.gl/does-not-exist');
    }
}
