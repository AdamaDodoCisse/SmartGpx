<?php

declare(strict_types=1);

namespace App\Routing\Enum;

/**
 * Classification interne SmartGPX (pas un identifiant Google) : STANDARD pour une requête qui
 * reste dans le calcul de route de base (modificateurs d'évitement, préférence trafic,
 * optimisation des étapes, qualité de la polyligne), ADVANCED dès qu'une fonctionnalité qui
 * élargit la forme de la requête Google est demandée (itinéraires alternatifs, route de
 * référence économe en carburant, calcul des péages). Ne détermine aujourd'hui aucun coût en
 * crédits — voir RouteOptionsCostClassifier et documentation/technique/routing-options.md.
 */
enum RoutingFeatureCostTier: string
{
    case STANDARD = 'STANDARD';
    case ADVANCED = 'ADVANCED';
}
