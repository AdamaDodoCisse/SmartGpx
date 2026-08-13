<?php

declare(strict_types=1);

namespace App\Usage\Action;

use App\Conversion\Entity\Conversion;
use App\Identity\Entity\User;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seul chemin de code qui écrit une ligne de ledger de type CONVERSION — une réservation
 * relâchée (ReleaseReservedCreditAction) n'en écrit jamais, une conversion échouée coûte donc
 * réellement 0.
 */
final class ConsumeReservedCreditAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreditAccountRepository $creditAccountRepository,
    ) {
    }

    public function execute(User $user, Conversion $conversion): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $creditAccount = $this->creditAccountRepository->findOneByUserOrFail($user);

            $this->entityManager->persist($conversion);
            // Flush intermédiaire : la ligne de ledger ci-dessous référence l'id de la
            // conversion, qui n'existe qu'une fois la ligne réellement insérée.
            $this->entityManager->flush();

            $balanceAfter = $this->creditAccountRepository->decrementReservedAndGetBalance($user);

            $this->entityManager->persist(new CreditTransaction(
                $creditAccount,
                CreditTransactionType::CONVERSION,
                -1,
                $balanceAfter,
                $conversion->getId(),
            ));
            $this->entityManager->flush();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
