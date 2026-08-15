<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Type d'émission du véhicule — mappé vers `routeModifiers.vehicleInfo.emissionType` sur l'API
 * Google Routes ; n'affecte que le calcul des péages dans certaines régions. Uniquement pertinent
 * quand l'estimation des péages est demandée (voir RoutingProviderCapabilities::$tollEstimation) ;
 * ne jamais afficher ce réglage sinon (progressive disclosure).
 */
enum VehicleEmissionType: string
{
    case GASOLINE = 'GASOLINE';
    case DIESEL = 'DIESEL';
    case ELECTRIC = 'ELECTRIC';
    case HYBRID = 'HYBRID';
}
