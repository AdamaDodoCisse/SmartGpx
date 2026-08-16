<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * Identifiant stable pour l'analytics (GTM/GA4), distinct du `publicId` (UUID) utilisé pour le
 * routing/checkout : un rapport GA4 est illisible avec des UUID comme item_id, et un UUID ne
 * survit de toute façon pas à la recréation d'une ligne CreditPack. Source unique, dérivée du
 * nombre de crédits plutôt que stockée en colonne — purement présentationnel, ne justifie pas un
 * schéma supplémentaire. Réutilisé par CreditPack::getAnalyticsSlug() (catalogue actif) et
 * CreditPurchase::getAnalyticsSlug() (figé au moment de l'achat), voir
 * documentation/technique/google-tag-manager.md.
 */
final class CreditPackSlug
{
    public static function forCredits(int $credits): string
    {
        return match ($credits) {
            10 => 'starter_10',
            100 => 'popular_100',
            500 => 'power_500',
            default => 'pack_'.$credits,
        };
    }
}
