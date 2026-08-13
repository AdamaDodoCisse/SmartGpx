<?php

declare(strict_types=1);

namespace App\Tests\Extension\Action;

use App\Extension\Action\GenerateExtensionAuthorizationAction;
use App\Extension\Action\RevokeExtensionAuthorizationAction;
use App\Extension\Exception\ExtensionAuthorizationNotFoundException;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\UuidV7;

final class RevokeExtensionAuthorizationActionTest extends KernelTestCase
{
    public function testItRevokesAnOwnedAuthorization(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = $this->createUser($entityManager);
        $generated = $container->get(GenerateExtensionAuthorizationAction::class)->execute($user);

        $container->get(RevokeExtensionAuthorizationAction::class)
            ->execute($user, (string) $generated->authorization->getPublicId());

        $repository = $container->get(ExtensionAuthorizationRepository::class);
        $stored = $repository->findOneByPublicIdForUser($user, (string) $generated->authorization->getPublicId());

        self::assertNotNull($stored);
        self::assertTrue($stored->isRevoked());
        // Toujours listable après révocation — jamais supprimée.
        self::assertCount(1, $repository->findAllForUser($user));
    }

    public function testRevokingAnUnknownPublicIdThrows(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = $this->createUser($entityManager);

        $this->expectException(ExtensionAuthorizationNotFoundException::class);
        $container->get(RevokeExtensionAuthorizationAction::class)
            ->execute($user, (string) new UuidV7());
    }

    public function testRevokingAnotherUsersAuthorizationThrows(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $owner = $this->createUser($entityManager);
        $intruder = $this->createUser($entityManager);
        $generated = $container->get(GenerateExtensionAuthorizationAction::class)->execute($owner);

        $this->expectException(ExtensionAuthorizationNotFoundException::class);
        $container->get(RevokeExtensionAuthorizationAction::class)
            ->execute($intruder, (string) $generated->authorization->getPublicId());
    }

    public function testRevokingAnAlreadyRevokedAuthorizationIsANoOp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = $this->createUser($entityManager);
        $generated = $container->get(GenerateExtensionAuthorizationAction::class)->execute($user);
        $publicId = (string) $generated->authorization->getPublicId();

        $revokeAction = $container->get(RevokeExtensionAuthorizationAction::class);
        $revokeAction->execute($user, $publicId);
        $revokeAction->execute($user, $publicId);

        $repository = $container->get(ExtensionAuthorizationRepository::class);
        $stored = $repository->findOneByPublicIdForUser($user, $publicId);

        self::assertNotNull($stored);
        self::assertTrue($stored->isRevoked());
        self::assertCount(1, $repository->findAllForUser($user));
    }

    private function createUser(EntityManagerInterface $entityManager): User
    {
        $user = new User(sprintf('revoke-ext-auth-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
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
