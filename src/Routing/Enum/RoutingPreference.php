<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Préférence de calcul d'itinéraire, indépendante du fournisseur (voir GoogleRoutesProvider pour
 * le mapping vers l'enum routingPreference de l'API Google Routes). Seule TRAFFIC_UNAWARE est
 * envoyée pour un mode de transport qui ne supporte pas le trafic (voir
 * RoutingProviderCapabilities::$trafficAware).
 */
enum RoutingPreference: string
{
    case TRAFFIC_UNAWARE = 'TRAFFIC_UNAWARE';
    case TRAFFIC_AWARE = 'TRAFFIC_AWARE';
    case TRAFFIC_AWARE_OPTIMAL = 'TRAFFIC_AWARE_OPTIMAL';
}
