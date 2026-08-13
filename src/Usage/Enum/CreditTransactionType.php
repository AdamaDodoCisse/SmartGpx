<?php

declare(strict_types=1);

namespace App\Usage\Enum;

/**
 * WELCOME et CONVERSION sont les seuls types produits par du code en Phase 2.
 * PURCHASE/REFUND (Phase 4 — Stripe) et ADMIN_ADJUSTMENT (Phase 8 — admin) existent déjà dans
 * l'enum pour que le schéma du ledger n'ait pas besoin d'une migration cassante plus tard.
 */
enum CreditTransactionType: string
{
    case WELCOME = 'welcome';
    case PURCHASE = 'purchase';
    case CONVERSION = 'conversion';
    case REFUND = 'refund';
    case ADMIN_ADJUSTMENT = 'admin_adjustment';
}
