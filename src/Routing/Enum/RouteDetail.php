<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Densité de la géométrie renvoyée par le fournisseur — mappé vers `polylineQuality` sur l'API
 * Google Routes (OVERVIEW pour STANDARD, HIGH_QUALITY pour HIGH_QUALITY). STANDARD reproduit le
 * comportement implicite d'avant les options avancées (aucun `polylineQuality` n'était envoyé).
 */
enum RouteDetail: string
{
    case STANDARD = 'STANDARD';
    case HIGH_QUALITY = 'HIGH_QUALITY';
}
