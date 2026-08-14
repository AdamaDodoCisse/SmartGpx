<?php

declare(strict_types=1);

namespace App\Billing\Enum;

/**
 * UNHANDLED couvre tout événement Stripe reconnu par le SDK mais hors périmètre de cette phase
 * (payment_intent.payment_failed, charge.refunded, ...) — le webhook y répond 200 sans action,
 * plutôt que de lever une exception. Ajouter un nouveau type géré plus tard n'est qu'un nouveau
 * cas d'enum + un nouveau bras de match, sans changement d'interface.
 */
enum WebhookEventType
{
    case CHECKOUT_SESSION_COMPLETED;
    case UNHANDLED;
}
