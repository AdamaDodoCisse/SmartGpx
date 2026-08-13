<?php

declare(strict_types=1);

namespace App\Conversion\Parser;

use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Routing\Enum\TravelMode;
use App\Routing\ValueObject\RouteLocationParser;

/**
 * Aucune spécification officielle de Google ne couvre ces formats d'URL — voir
 * documentation/technique/google-maps-to-gpx.md pour le détail des niveaux de support.
 */
final class GoogleMapsUrlParser
{
    public function __construct(
        private readonly RouteLocationParser $routeLocationParser,
        private readonly GoogleMapsShortLinkResolver $shortLinkResolver,
    ) {
    }

    /**
     * @throws InvalidGoogleMapsUrlException
     * @throws UnsupportedGoogleMapsUrlException
     */
    public function parse(string $rawUrl): ParsedGoogleMapsUrl
    {
        $url = trim($rawUrl);

        if ('' === $url) {
            throw new InvalidGoogleMapsUrlException('The URL cannot be empty.');
        }

        if ($this->shortLinkResolver->isShortLink($url)) {
            $url = $this->shortLinkResolver->resolve($url);
        }

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['host'])) {
            throw new InvalidGoogleMapsUrlException('This is not a valid URL.');
        }

        $host = strtolower($parts['host']);

        if ('google.com' !== $host && !str_ends_with($host, '.google.com')) {
            throw new UnsupportedGoogleMapsUrlException('This does not look like a Google Maps link.');
        }

        $path = $parts['path'] ?? '';

        if (!str_starts_with($path, '/maps/dir')) {
            throw new UnsupportedGoogleMapsUrlException('This Google Maps link is not a directions link (e.g. a place, search, or view-only link).');
        }

        parse_str($parts['query'] ?? '', $query);

        if (isset($query['api']) && '1' === $query['api']) {
            return $this->parseDocumentedApiFormat($query);
        }

        return $this->parsePathSegmentFormat($path);
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private function parseDocumentedApiFormat(array $query): ParsedGoogleMapsUrl
    {
        $origin = $query['origin'] ?? null;
        $destination = $query['destination'] ?? null;

        if (!\is_string($origin) || '' === $origin || !\is_string($destination) || '' === $destination) {
            throw new UnsupportedGoogleMapsUrlException('This link is missing an origin or a destination.');
        }

        $intermediates = [];
        $waypoints = $query['waypoints'] ?? null;

        if (\is_string($waypoints) && '' !== $waypoints) {
            foreach (explode('|', $waypoints) as $waypoint) {
                $intermediates[] = $this->routeLocationParser->parse($waypoint);
            }
        }

        $travelMode = TravelMode::DRIVE;
        $travelModeInferred = true;
        $rawTravelMode = $query['travelmode'] ?? null;

        if (\is_string($rawTravelMode)) {
            $mapped = self::mapDocumentedTravelMode($rawTravelMode);

            if (null !== $mapped) {
                $travelMode = $mapped;
                $travelModeInferred = false;
            }
        }

        return new ParsedGoogleMapsUrl(
            origin: $this->routeLocationParser->parse($origin),
            destination: $this->routeLocationParser->parse($destination),
            intermediates: $intermediates,
            travelMode: $travelMode,
            travelModeInferred: $travelModeInferred,
        );
    }

    private function parsePathSegmentFormat(string $path): ParsedGoogleMapsUrl
    {
        $rest = substr($path, \strlen('/maps/dir/'));
        $rawSegments = array_filter(
            explode('/', $rest),
            static fn (string $segment): bool => '' !== $segment
                && !str_starts_with($segment, '@')
                && !str_starts_with($segment, 'data='),
        );

        $segments = array_values(array_map(urldecode(...), $rawSegments));

        if (\count($segments) < 2) {
            throw new UnsupportedGoogleMapsUrlException('Could not find both an origin and a destination in this link.');
        }

        $origin = $segments[0];
        $destination = $segments[\count($segments) - 1];
        $intermediates = \array_slice($segments, 1, -1);

        return new ParsedGoogleMapsUrl(
            origin: $this->routeLocationParser->parse($origin),
            destination: $this->routeLocationParser->parse($destination),
            intermediates: array_map($this->routeLocationParser->parse(...), $intermediates),
            travelMode: TravelMode::DRIVE,
            travelModeInferred: true,
        );
    }

    private static function mapDocumentedTravelMode(string $raw): ?TravelMode
    {
        return match (strtolower($raw)) {
            'driving' => TravelMode::DRIVE,
            'walking' => TravelMode::WALK,
            'bicycling' => TravelMode::BICYCLE,
            'transit' => TravelMode::TRANSIT,
            default => null,
        };
    }
}
