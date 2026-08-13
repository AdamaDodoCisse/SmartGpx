<?php

declare(strict_types=1);

namespace App\Usage\Action;

use App\Identity\Entity\User;
use App\Usage\Entity\CreditAccount;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use Doctrine\ORM\EntityManagerInterface;

final class GrantWelcomeCreditAction
{
    private const int WELCOME_CREDITS = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user): void
    {
        $creditAccount = new CreditAccount($user);
        $creditAccount->initializeBalance(self::WELCOME_CREDITS);

        $this->entityManager->persist($creditAccount);
        // Flush immédiat : la ligne de ledger ci-dessous référence le compte, qui doit déjà
        // exister en base (contrainte FK non-nullable sur credit_transaction.credit_account_id).
        $this->entityManager->flush();

        $this->entityManager->persist(new CreditTransaction(
            $creditAccount,
            CreditTransactionType::WELCOME,
            self::WELCOME_CREDITS,
            self::WELCOME_CREDITS,
        ));
        $this->entityManager->flush();
    }
}
