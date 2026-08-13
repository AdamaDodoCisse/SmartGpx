<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    public function execute(User $user, string $fullToken, string $newPlainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPlainPassword));
        $this->entityManager->flush();

        $this->resetPasswordHelper->removeResetRequest($fullToken);
    }
}
