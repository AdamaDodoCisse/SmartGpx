<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

use App\Routing\Enum\VehicleEmissionType;

/**
 * Ne construire que lorsque l'estimation des péages est effectivement demandée — voir
 * RouteOptions::$vehicleProfile et le principe de progressive disclosure du panneau d'options
 * avancées (documentation/fonctionnel/advanced-route-options.md).
 */
final readonly class VehicleProfile
{
    public function __construct(
        public VehicleEmissionType $emissionType,
    ) {
    }
}
