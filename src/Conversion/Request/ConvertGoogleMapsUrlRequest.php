<?php

declare(strict_types=1);

namespace App\Conversion\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tous les champs d'options avancées sont optionnels et nullables : un client qui n'envoie que
 * {url, travelMode} (l'extension Chrome aujourd'hui) obtient exactement le comportement d'avant
 * les options avancées — voir GoogleMapsRouteOptionsMapper, qui construit un RouteOptions par
 * défaut quand ces champs sont absents.
 */
final class ConvertGoogleMapsUrlRequest
{
    #[Assert\NotBlank]
    #[Assert\Url]
    public string $url = '';

    /**
     * Sélecteur de mode de transport explicite depuis l'UI — prime toujours sur un mode déduit
     * de l'URL (voir GoogleMapsUrlParser, format "chemin" où le mode n'est jamais fiable).
     */
    public ?string $travelMode = null;

    /**
     * Nom d'un RoutePreset — quand présent, résolu en RouteOptions par
     * RoutePresetOptionsResolver ; tout champ ci-dessous explicitement renseigné dans la même
     * requête l'emporte sur le champ correspondant du preset.
     */
    public ?string $preset = null;

    public ?bool $avoidHighways = null;
    public ?bool $avoidTolls = null;
    public ?bool $avoidFerries = null;
    public ?string $routingPreference = null;
    public ?bool $optimizeWaypointOrder = null;
    public ?string $routeDetail = null;
    public ?bool $showAlternativeRoutes = null;
    public ?bool $showFuelEfficientRoute = null;
    public ?bool $showTollEstimates = null;
    public ?string $vehicleEmissionType = null;

    /**
     * Un type ('STOP'|'VIA') par étape intermédiaire, dans l'ordre où GoogleMapsUrlParser les a
     * extraites de l'URL — toute étape omise (tableau plus court que le nombre d'étapes réelles)
     * est traitée comme STOP par défaut.
     *
     * @var list<string>|null
     */
    public ?array $waypointTypes = null;

    /**
     * Extraction depuis un tableau JSON décodé — partagée par tous les contrôleurs qui acceptent
     * ce DTO (web, extension, prévisualisation), pour ne pas dupliquer la lecture défensive de
     * chaque champ optionnel.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $dto = new self();
        $dto->url = \is_string($payload['url'] ?? null) ? $payload['url'] : '';
        $dto->travelMode = self::stringOrNull($payload['travelMode'] ?? null);
        $dto->preset = self::stringOrNull($payload['preset'] ?? null);
        $dto->avoidHighways = self::boolOrNull($payload['avoidHighways'] ?? null);
        $dto->avoidTolls = self::boolOrNull($payload['avoidTolls'] ?? null);
        $dto->avoidFerries = self::boolOrNull($payload['avoidFerries'] ?? null);
        $dto->routingPreference = self::stringOrNull($payload['routingPreference'] ?? null);
        $dto->optimizeWaypointOrder = self::boolOrNull($payload['optimizeWaypointOrder'] ?? null);
        $dto->routeDetail = self::stringOrNull($payload['routeDetail'] ?? null);
        $dto->showAlternativeRoutes = self::boolOrNull($payload['showAlternativeRoutes'] ?? null);
        $dto->showFuelEfficientRoute = self::boolOrNull($payload['showFuelEfficientRoute'] ?? null);
        $dto->showTollEstimates = self::boolOrNull($payload['showTollEstimates'] ?? null);
        $dto->vehicleEmissionType = self::stringOrNull($payload['vehicleEmissionType'] ?? null);

        $waypointTypes = $payload['waypointTypes'] ?? null;
        $dto->waypointTypes = \is_array($waypointTypes)
            ? array_values(array_filter($waypointTypes, static fn (mixed $value): bool => \is_string($value)))
            : null;

        return $dto;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function boolOrNull(mixed $value): ?bool
    {
        return \is_bool($value) ? $value : null;
    }
}
