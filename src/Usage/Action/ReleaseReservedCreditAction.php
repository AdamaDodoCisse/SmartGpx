<?php

declare(strict_types=1);

namespace App\Usage\Action;

use App\Identity\Entity\User;
use App\Usage\Repository\CreditAccountRepository;

final class ReleaseReservedCreditAction
{
    public function __construct(
        private readonly CreditAccountRepository $creditAccountRepository,
    ) {
    }

    public function execute(User $user): void
    {
        $this->creditAccountRepository->releaseOneReservation($user);
    }
}
