<?php

declare(strict_types=1);

namespace App\Usage\Action;

use App\Identity\Entity\User;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seul chemin de code qui produit CreditTransactionType::ADMIN_ADJUSTMENT — toujours un crédit,
 * jamais un débit (voir documentation/decisions/ADR-007-admin-access-control.md).
 */
final class GrantAdminCreditAdjustmentAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreditAccountRepository $creditAccountRepository,
    ) {
    }

    public function execute(User $targetUser, int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Admin credit adjustments must be a positive amount.');
        }

        $creditAccount = $this->creditAccountRepository->findOneByUserOrFail($targetUser);
        $balanceAfter = $this->creditAccountRepository->creditBalance($targetUser, $amount);

        $this->entityManager->persist(new CreditTransaction(
            $creditAccount,
            CreditTransactionType::ADMIN_ADJUSTMENT,
            $amount,
            $balanceAfter,
        ));
        $this->entityManager->flush();
    }
}
