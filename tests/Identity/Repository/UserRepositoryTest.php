<?php

declare(strict_types=1);

namespace App\Tests\Identity\Repository;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    public function testFindOneByGoogleIdFindsAMatchingUser(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $userRepository = $container->get(UserRepository::class);

        $googleId = uniqid('google-');
        $user = new User(sprintf('google-repo-%s@example.com', uniqid()));
        $user->setGoogleId($googleId);
        $entityManager->persist($user);
        $entityManager->flush();

        $found = $userRepository->findOneByGoogleId($googleId);

        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindOneByGoogleIdReturnsNullWhenNoMatch(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        self::assertNull($userRepository->findOneByGoogleId(uniqid('missing-')));
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
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
