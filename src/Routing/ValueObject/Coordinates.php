<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

/**
 * Règle de non-régression critique : une chaîne comme "49.051624,2.0093594" doit être reconnue
 * comme des coordonnées GPS, jamais envoyée comme adresse littérale au fournisseur de routing.
 * tryParse() est le point d'application unique de cette règle (voir RouteLocationParser).
 */
final class Coordinates implements RouteLocation
{
    private const string PATTERN = '/^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/';

    private function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
    }

    /**
     * Retourne null pour toute chaîne qui n'a pas la forme "lat,lng" ou dont les valeurs sont
     * hors plage — l'appelant doit alors se rabattre sur Address::fromString().
     */
    public static function tryParse(string $raw): ?self
    {
        if (1 !== preg_match(self::PATTERN, $raw, $matches)) {
            return null;
        }

        $latitude = (float) $matches[1];
        $longitude = (float) $matches[2];

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        return new self($latitude, $longitude);
    }

    public static function fromValues(float $latitude, float $longitude): self
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException(sprintf('Latitude must be between -90 and 90, got %s.', $latitude));
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException(sprintf('Longitude must be between -180 and 180, got %s.', $longitude));
        }

        return new self($latitude, $longitude);
    }

    /**
     * @return array{location: array{latLng: array{latitude: float, longitude: float}}}
     */
    public function toGoogleWaypoint(): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                ],
            ],
        ];
    }

    public function label(): string
    {
        return sprintf('%s, %s', $this->latitude, $this->longitude);
    }
}
