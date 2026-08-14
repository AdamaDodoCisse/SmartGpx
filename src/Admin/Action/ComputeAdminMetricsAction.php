<?php

declare(strict_types=1);

namespace App\Admin\Action;

use App\Admin\Metrics\AdminMetrics;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Conversion\Repository\ConversionFailureRepository;
use App\Conversion\Repository\ConversionRepository;
use App\Identity\Repository\UserRepository;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditTransactionRepository;

/**
 * Lecture transversale pure, ne touche aucune entité — n'appartient à aucun domaine existant,
 * seule raison d'être de src/Admin/.
 */
final class ComputeAdminMetricsAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CreditTransactionRepository $creditTransactionRepository,
        private readonly ConversionRepository $conversionRepository,
        private readonly ConversionFailureRepository $conversionFailureRepository,
        private readonly CreditPurchaseRepository $creditPurchaseRepository,
        private readonly CreditPackRepository $creditPackRepository,
    ) {
    }

    public function execute(): AdminMetrics
    {
        $creditsIssued = $this->creditTransactionRepository->sumAmountByTypes([
            CreditTransactionType::WELCOME,
            CreditTransactionType::PURCHASE,
            CreditTransactionType::ADMIN_ADJUSTMENT,
        ]);
        $creditsConsumed = $this->creditTransactionRepository->sumAmountByTypes([
            CreditTransactionType::CONVERSION,
        ]);

        return new AdminMetrics(
            totalUsers: $this->userRepository->count([]),
            totalCreditsIssued: $creditsIssued,
            totalCreditsConsumed: abs($creditsConsumed),
            totalSuccessfulConversions: $this->conversionRepository->count([]),
            totalFailedConversions: $this->conversionFailureRepository->count([]),
            totalCompletedPurchases: $this->creditPurchaseRepository->countCompleted(),
            totalRevenueCents: $this->creditPurchaseRepository->sumAmountCentsCompleted(),
            activeCreditPackCount: $this->creditPackRepository->count(['active' => true]),
        );
    }
}
