<?php

declare(strict_types=1);

namespace App\Conversion\Exception;

/**
 * L'aperçu d'itinéraires référencé par previewId est introuvable, expiré (TTL du cache), ou
 * n'appartient pas à l'utilisateur courant — les trois cas renvoient la même exception générique,
 * pour ne jamais révéler l'existence d'un aperçu à un autre utilisateur.
 */
final class RoutePreviewNotFoundException extends \RuntimeException
{
}
