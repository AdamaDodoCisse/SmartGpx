<?php

declare(strict_types=1);

namespace App\Usage\Action;

use App\Identity\Entity\User;
use App\Usage\Exception\InsufficientCreditsException;
use App\Usage\Repository\CreditAccountRepository;

final class ReserveCreditAction
{
    public function __construct(
        private readonly CreditAccountRepository $creditAccountRepository,
    ) {
    }

    /**
     * @throws InsufficientCreditsException
     */
    public function execute(User $user): void
    {
        if (!$this->creditAccountRepository->reserveOne($user)) {
            throw new InsufficientCreditsException($user);
        }
    }
}
