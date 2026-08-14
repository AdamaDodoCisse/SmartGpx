<?php

declare(strict_types=1);

namespace App\Billing\Enum;

/**
 * FAILED est pré-provisionné mais inutilisé cette phase (aucun chemin de code ne le construit
 * encore) — même logique que CreditTransactionType::ADMIN_ADJUSTMENT en Phase 2.
 */
enum CreditPurchaseStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
