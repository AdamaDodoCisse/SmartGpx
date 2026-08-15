<?php

declare(strict_types=1);

namespace App\Routing\Service;

use App\Routing\Enum\RouteDetail;
use App\Routing\Enum\RoutePreset;
use App\Routing\Enum\TravelMode;
use App\Routing\ValueObject\RouteModifiers;
use App\Routing\ValueObject\RouteOptions;
use App\Routing\ValueObject\RoutePresetResolution;

/**
 * Une seule table de correspondance preset → RouteOptions (match, pas une multitude de if) — le
 * backend est la source de vérité unique : le frontend n'a besoin de connaître que les noms et
 * libellés des presets, pas leur résolution (voir documentation/fonctionnel/advanced-route-options.md).
 * Un preset ne doit jamais prétendre à une capacité que notre domaine ne peut pas réellement
 * fournir — voir la remarque sur "Scenic" dans le brief produit.
 */
final class RoutePresetOptionsResolver
{
    public function resolve(RoutePreset $preset): RoutePresetResolution
    {
        return match ($preset) {
            RoutePreset::FASTEST => new RoutePresetResolution(new RouteOptions(), null),
            RoutePreset::ROAD_TRIP => new RoutePresetResolution(
                new RouteOptions(routeDetail: RouteDetail::HIGH_QUALITY),
                TravelMode::DRIVE,
            ),
            RoutePreset::MOTORCYCLE => new RoutePresetResolution(
                new RouteOptions(modifiers: new RouteModifiers(avoidHighways: true)),
                TravelMode::TWO_WHEELER,
            ),
        };
    }
}
