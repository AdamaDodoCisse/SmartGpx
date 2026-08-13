<?php

declare(strict_types=1);

namespace App\Conversion\Exception;

/**
 * L'entrée n'est même pas une URL exploitable (vide, syntaxiquement invalide).
 */
final class InvalidGoogleMapsUrlException extends \RuntimeException
{
}
