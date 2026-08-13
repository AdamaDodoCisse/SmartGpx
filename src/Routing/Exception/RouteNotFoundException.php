<?php

declare(strict_types=1);

namespace App\Routing\Exception;

/**
 * Le fournisseur a répondu avec succès mais n'a trouvé aucun itinéraire entre les points donnés
 * (ex. aucune route routière entre deux points séparés par un océan). Cas utilisateur légitime,
 * à afficher tel quel — contrairement à RoutingProviderUnavailableException.
 */
final class RouteNotFoundException extends RoutingProviderException
{
}
