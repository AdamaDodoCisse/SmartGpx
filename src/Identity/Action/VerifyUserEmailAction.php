<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class VerifyUserEmailAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user): void
    {
        if ($user->isVerified()) {
            return;
        }

        $user->setVerified(true);
        $this->entityManager->flush();
    }
}
