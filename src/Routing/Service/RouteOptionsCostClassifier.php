<?php

declare(strict_types=1);

namespace App\Routing\Service;

use App\Routing\Enum\RoutingFeatureCostTier;
use App\Routing\ValueObject\RouteOptions;

/**
 * Classifie une requête d'options avancées en STANDARD ou ADVANCED — une classification interne
 * SmartGPX, pas un tarif Google. ADVANCED dès qu'une option élargit la forme de la requête Google
 * au-delà du calcul de route de base (itinéraires alternatifs, route de référence économe en
 * carburant, calcul des péages) ; ces trois fonctionnalités correspondent aux champs
 * `computeAlternativeRoutes`, `requestedReferenceRoutes` et `extraComputations` de l'API Google
 * Routes, documentés comme relevant d'un palier tarifaire différent du calcul de route de base.
 * Voir documentation/technique/routing-options.md et ADR-008.
 *
 * Ne détermine aujourd'hui aucun coût en crédits réel — voir RouteOptions et Usage/.
 */
final class RouteOptionsCostClassifier
{
    public function classify(RouteOptions $options): RoutingFeatureCostTier
    {
        $usesAdvancedGoogleFeature = $options->computeAlternativeRoutes
            || $options->showFuelEfficientRoute
            || $options->showTollEstimates;

        return $usesAdvancedGoogleFeature
            ? RoutingFeatureCostTier::ADVANCED
            : RoutingFeatureCostTier::STANDARD;
    }
}
