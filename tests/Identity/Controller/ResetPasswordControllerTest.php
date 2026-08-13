<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Tests\Support\EmailLinkExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ResetPasswordControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testUserCanRequestAndCompleteAPasswordReset(): void
    {
        $client = static::createClient();
        $email = sprintf('reset-%s@example.com', uniqid());
        $this->createVerifiedUser($email, 'old-password-123');

        $client->request('GET', '/forgot-password');
        self::assertResponseIsSuccessful();

        $client->submitForm('forgot_password_submit', [
            'forgot_password_form[email]' => $email,
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);

        $resetUrl = EmailLinkExtractor::firstLink(self::getMailerMessage(0));

        // Premier GET : capture le token en session puis redirige vers /reset-password sans token dans l'URL.
        $client->request('GET', $resetUrl);
        self::assertResponseRedirects('/reset-password');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->submitForm('reset_password_submit', [
            'change_password_form[plainPassword]' => 'new-password-456',
        ]);

        self::assertResponseRedirects('/login');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $updatedUser = $userRepository->findOneByEmail($email);
        self::assertNotNull($updatedUser);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($updatedUser, 'new-password-456'));
    }

    public function testRequestingAResetForAnUnknownEmailStillShowsTheGenericMessage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/forgot-password');
        $client->submitForm('forgot_password_submit', [
            'forgot_password_form[email]' => 'no-such-account@example.com',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }

    private function createVerifiedUser(string $email, string $password): void
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $container->get(UserRepository::class);

        foreach ($repository->findAll() as $user) {
            if (str_contains($user->getEmail(), '@example.com')) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
