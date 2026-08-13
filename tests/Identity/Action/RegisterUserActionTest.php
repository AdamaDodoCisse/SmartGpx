<?php

declare(strict_types=1);

namespace App\Tests\Identity\Action;

use App\Identity\Action\RegisterUserAction;
use App\Identity\Enum\AuthProvider;
use App\Identity\Exception\EmailAlreadyUsedException;
use App\Identity\Repository\UserRepository;
use App\Identity\Request\RegisterUserRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserActionTest extends KernelTestCase
{
    public function testItRegistersAnUnverifiedUserWithAHashedPassword(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $action = $container->get(RegisterUserAction::class);

        $request = new RegisterUserRequest();
        $request->email = sprintf('register-%s@example.com', uniqid());
        $request->plainPassword = 'correct-horse-battery-staple';

        $user = $action->execute($request);

        self::assertSame($request->email, $user->getEmail());
        self::assertNotSame($request->plainPassword, $user->getPassword());
        self::assertFalse($user->isVerified());
        self::assertNull($user->getVerifiedAt());
        self::assertSame(AuthProvider::LOCAL, $user->getAuthProvider());
        self::assertSame(['ROLE_USER'], $user->getRoles());

        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, $request->plainPassword));
    }

    public function testItRejectsRegistrationWithAnAlreadyUsedEmail(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $action = $container->get(RegisterUserAction::class);

        $request = new RegisterUserRequest();
        $request->email = sprintf('duplicate-%s@example.com', uniqid());
        $request->plainPassword = 'correct-horse-battery-staple';

        $action->execute($request);

        $this->expectException(EmailAlreadyUsedException::class);
        $action->execute($request);
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
