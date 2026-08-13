<?php

declare(strict_types=1);

namespace App\Extension\Action;

use App\Extension\Entity\ExtensionAuthorization;
use App\Extension\Result\GeneratedExtensionAuthorization;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class GenerateExtensionAuthorizationAction
{
    private const string TOKEN_PREFIX = 'sgpx_ext_';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(User $user, ?string $label = null): GeneratedExtensionAuthorization
    {
        $plainToken = self::TOKEN_PREFIX.bin2hex(random_bytes(32));

        $authorization = new ExtensionAuthorization($user, hash('sha256', $plainToken), $label);

        $this->entityManager->persist($authorization);
        $this->entityManager->flush();

        return new GeneratedExtensionAuthorization($authorization, $plainToken);
    }
}
