<?php

declare(strict_types=1);

namespace App\Conversion\ValueObject;

use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\ValueObject\RouteOptions;

/**
 * Résultat de GoogleMapsRouteOptionsMapper::map() : les options réellement applicables (déjà
 * filtrées par les capabilities du fournisseur actif), le type de chaque étape intermédiaire dans
 * l'ordre soumis, et un mode de transport suggéré par le preset choisi, le cas échéant (une
 * sélection explicite de travelMode dans la même requête l'emporte toujours — voir
 * ConvertGoogleMapsToGpxAction).
 */
final readonly class GoogleMapsRouteOptionsMapping
{
    /**
     * @param list<WaypointType> $waypointTypes
     */
    public function __construct(
        public RouteOptions $options,
        public array $waypointTypes,
        public ?TravelMode $presetSuggestedTravelMode,
    ) {
    }
}
