<?php

declare(strict_types=1);

namespace App\Tests\Identity\Action;

use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PromoteUserToAdminActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PromoteUserToAdminAction $action;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(PromoteUserToAdminAction::class);

        $this->user = new User(sprintf('promote-admin-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testItGrantsRoleAdmin(): void
    {
        self::assertNotContains('ROLE_ADMIN', $this->user->getRoles());

        $this->action->execute($this->user);

        self::assertContains('ROLE_ADMIN', $this->user->getRoles());
    }

    public function testItIsIdempotent(): void
    {
        $this->action->execute($this->user);
        $this->action->execute($this->user);

        $roles = $this->user->getRoles();
        self::assertCount(2, $roles);
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
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
