<?php

declare(strict_types=1);

namespace App\Conversion\Exception;

/**
 * L'URL est syntaxiquement valide et reconnue comme un lien Google Maps, mais pas dans une forme
 * supportée (lien de consultation seule, recherche, lien à un seul point, hôte non-Google, ou
 * lien court impossible à résoudre).
 */
final class UnsupportedGoogleMapsUrlException extends \RuntimeException
{
}
