<?php

declare(strict_types=1);

namespace App\Conversion\Enum;

/**
 * Miroir exact des clés de traduction conversion.error.{unsupported_url,insufficient_credits,
 * route_not_found,provider_unavailable} — les seules exceptions correspondant à une vraie
 * tentative de conversion. invalid_csrf/too_many_requests sont des gardes avant toute tentative
 * réelle et n'ont pas d'équivalent ici.
 */
enum ConversionFailureReason: string
{
    case UNSUPPORTED_URL = 'unsupported_url';
    case INSUFFICIENT_CREDITS = 'insufficient_credits';
    case ROUTE_NOT_FOUND = 'route_not_found';
    case PROVIDER_UNAVAILABLE = 'provider_unavailable';
}
