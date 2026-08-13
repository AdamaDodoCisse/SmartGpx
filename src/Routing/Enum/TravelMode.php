<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Modes de transport supportés, indépendants du fournisseur de routing (voir GoogleRoutesProvider
 * pour le mapping vers l'enum travelMode de l'API Google Routes).
 */
enum TravelMode: string
{
    case DRIVE = 'DRIVE';
    case WALK = 'WALK';
    case BICYCLE = 'BICYCLE';
    case TWO_WHEELER = 'TWO_WHEELER';
    case TRANSIT = 'TRANSIT';
}
