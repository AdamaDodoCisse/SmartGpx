<?php

declare(strict_types=1);

namespace App\Conversion\Parser;

use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleMapsShortLinkResolver
{
    /** @var list<string> */
    private const array SHORT_LINK_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    public function __construct(
        #[Autowire(service: 'google.maps.shortlink.client')]
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function isShortLink(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && \in_array(strtolower($host), self::SHORT_LINK_HOSTS, true);
    }

    /**
     * Résout un lien court en suivant la redirection HTTP et renvoie l'URL finale.
     * Un GET est utilisé plutôt qu'un HEAD : certains redirecteurs Google se comportent de
     * façon incohérente avec HEAD.
     *
     * @throws UnsupportedGoogleMapsUrlException
     */
    public function resolve(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $response->getStatusCode();
            $finalUrl = $response->getInfo('url');
        } catch (TransportExceptionInterface $exception) {
            throw new UnsupportedGoogleMapsUrlException('Unable to resolve the short link.', previous: $exception);
        }

        if (!\is_string($finalUrl) || '' === $finalUrl) {
            throw new UnsupportedGoogleMapsUrlException('Unable to resolve the short link.');
        }

        return $finalUrl;
    }
}
