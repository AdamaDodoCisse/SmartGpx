<?php

declare(strict_types=1);

namespace App\Extension\Action;

use App\Extension\Exception\ExtensionAuthorizationNotFoundException;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RevokeExtensionAuthorizationAction
{
    public function __construct(
        private readonly ExtensionAuthorizationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ExtensionAuthorizationNotFoundException si l'autorisation n'existe pas ou n'appartient pas à $user
     */
    public function execute(User $user, string $publicId): void
    {
        $authorization = $this->repository->findOneByPublicIdForUser($user, $publicId);

        if (null === $authorization) {
            throw new ExtensionAuthorizationNotFoundException();
        }

        $authorization->revoke();
        $this->entityManager->flush();
    }
}
