<?php

declare(strict_types=1);

namespace App\Tests\Extension\Action;

use App\Extension\Action\GenerateExtensionAuthorizationAction;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GenerateExtensionAuthorizationActionTest extends KernelTestCase
{
    public function testItPersistsAnAuthorizationWithOnlyTheHashedToken(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $action = $container->get(GenerateExtensionAuthorizationAction::class);

        $user = new User(sprintf('gen-ext-auth-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $result = $action->execute($user, 'Chrome extension');

        self::assertStringStartsWith('sgpx_ext_', $result->plainToken);
        self::assertSame('Chrome extension', $result->authorization->getLabel());
        self::assertFalse($result->authorization->isRevoked());

        $expectedHash = hash('sha256', $result->plainToken);
        $repository = $container->get(ExtensionAuthorizationRepository::class);
        $stored = $repository->findActiveByTokenHash($expectedHash);

        self::assertNotNull($stored);
        self::assertSame($result->authorization->getId(), $stored->getId());

        // Le jeton en clair n'est jamais retrouvable via son propre hash littéral.
        self::assertNull($repository->findActiveByTokenHash($result->plainToken));
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(ExtensionAuthorizationRepository::class)->findAll() as $authorization) {
            $entityManager->remove($authorization);
        }
        $entityManager->flush();

        $userRepository = $container->get(UserRepository::class);
        foreach ($userRepository->findAll() as $user) {
            if (str_contains($user->getEmail(), '@example.com')) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
