<?php

declare(strict_types=1);

namespace App\Tests\Identity\Action;

use App\Identity\Action\AuthenticateWithGoogleAction;
use App\Identity\Entity\User;
use App\Identity\Enum\AuthProvider;
use App\Identity\Exception\GoogleEmailNotVerifiedException;
use App\Identity\Repository\UserRepository;
use App\Identity\ValueObject\GoogleIdentity;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticateWithGoogleActionTest extends KernelTestCase
{
    public function testItCreatesAVerifiedGoogleOnlyUserAndGrantsWelcomeCredit(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $action = $container->get(AuthenticateWithGoogleAction::class);

        $email = sprintf('new-google-%s@example.com', uniqid());
        $identity = new GoogleIdentity(googleId: uniqid('google-'), email: $email, emailVerified: true);

        $user = $action->execute($identity);

        self::assertSame($email, $user->getEmail());
        self::assertSame($identity->googleId, $user->getGoogleId());
        self::assertSame(AuthProvider::GOOGLE, $user->getAuthProvider());
        self::assertTrue($user->isVerified());
        self::assertNull($user->getPassword());

        $creditAccountRepository = $container->get(CreditAccountRepository::class);
        $creditAccount = $creditAccountRepository->findOneByUser($user);
        self::assertNotNull($creditAccount);
        self::assertSame(1, $creditAccount->getBalance());
    }

    public function testItReturnsTheSameUserOnRepeatedGoogleLogin(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $action = $container->get(AuthenticateWithGoogleAction::class);

        $identity = new GoogleIdentity(
            googleId: uniqid('google-'),
            email: sprintf('repeat-google-%s@example.com', uniqid()),
            emailVerified: true,
        );

        $first = $action->execute($identity);
        $second = $action->execute($identity);

        self::assertSame($first->getId(), $second->getId());
    }

    public function testItLinksAnExistingLocalAccountByVerifiedEmailWithoutTouchingThePassword(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = sprintf('local-%s@example.com', uniqid());
        $localUser = new User($email);
        $localUser->setPassword($passwordHasher->hashPassword($localUser, 'correct-horse-battery-staple'));
        $localUser->setVerified(true);
        $entityManager->persist($localUser);
        $entityManager->flush();
        $originalPasswordHash = $localUser->getPassword();

        $action = $container->get(AuthenticateWithGoogleAction::class);
        $identity = new GoogleIdentity(googleId: uniqid('google-'), email: $email, emailVerified: true);

        $linked = $action->execute($identity);

        self::assertSame($localUser->getId(), $linked->getId());
        self::assertSame($identity->googleId, $linked->getGoogleId());
        self::assertSame($originalPasswordHash, $linked->getPassword());
        self::assertSame(AuthProvider::LOCAL, $linked->getAuthProvider());
    }

    public function testItRefusesToLinkOnAnUnverifiedGoogleEmail(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $email = sprintf('unverified-%s@example.com', uniqid());
        $localUser = new User($email);
        $localUser->setPassword('irrelevant-hash');
        $localUser->setVerified(true);
        $entityManager->persist($localUser);
        $entityManager->flush();

        $action = $container->get(AuthenticateWithGoogleAction::class);
        $identity = new GoogleIdentity(googleId: uniqid('google-'), email: $email, emailVerified: false);

        $this->expectException(GoogleEmailNotVerifiedException::class);
        $action->execute($identity);

        self::assertNull($localUser->getGoogleId());
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
