<?php

declare(strict_types=1);

namespace App\Routing\Exception;

/**
 * Le nombre d'étapes intermédiaires dépasse RoutingProviderCapabilities::$maxIntermediateWaypoints
 * du fournisseur actif — jamais une valeur codée en dur ailleurs dans le code (25 pour Google
 * Routes API aujourd'hui). Cas d'entrée utilisateur légitime à afficher tel quel, comme
 * RouteNotFoundException — pas une indisponibilité du fournisseur.
 */
final class TooManyWaypointsException extends RoutingProviderException
{
}
