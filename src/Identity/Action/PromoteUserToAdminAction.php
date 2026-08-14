<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class PromoteUserToAdminAction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function execute(User $user): void
    {
        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return;
        }

        $roles[] = 'ROLE_ADMIN';
        $user->setRoles($roles);
        $this->entityManager->flush();
    }
}
