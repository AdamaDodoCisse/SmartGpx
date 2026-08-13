<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Repository\UserRepository;
use App\Tests\Support\EmailLinkExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testUserCanRegisterAndVerifyTheirEmail(): void
    {
        $client = static::createClient();
        $email = sprintf('functional-%s@example.com', uniqid());

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('registration_submit', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'correct-horse-battery-staple',
        ]);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        $verificationUrl = EmailLinkExtractor::firstLink(self::getMailerMessage(0));

        $client->request('GET', $verificationUrl);
        self::assertResponseRedirects('/login');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $verifiedUser = $userRepository->findOneByEmail($email);
        self::assertNotNull($verifiedUser);
        self::assertTrue($verifiedUser->isVerified());
        self::assertNotNull($verifiedUser->getVerifiedAt());
    }

    public function testRegistrationWithAnAlreadyUsedEmailShowsAFormError(): void
    {
        $client = static::createClient();
        $email = sprintf('dup-functional-%s@example.com', uniqid());

        $client->request('GET', '/register');
        $client->submitForm('registration_submit', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'correct-horse-battery-staple',
        ]);

        $client->request('GET', '/register');
        $client->submitForm('registration_submit', [
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'correct-horse-battery-staple',
        ]);

        self::assertSelectorTextContains('body', 'already exists');
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
