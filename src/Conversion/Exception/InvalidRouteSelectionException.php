<?php

declare(strict_types=1);

namespace App\Conversion\Exception;

/**
 * L'index sélectionné ne correspond à aucun itinéraire candidat de l'aperçu référencé.
 */
final class InvalidRouteSelectionException extends \RuntimeException
{
}
