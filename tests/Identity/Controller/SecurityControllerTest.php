<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    private string $email;
    private string $password = 'correct-horse-battery-staple';

    public function testAVerifiedUserCanLogIn(): void
    {
        $client = static::createClient();
        $this->createVerifiedUser($client);

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('login_submit', [
            '_username' => $this->email,
            '_password' => $this->password,
        ]);

        self::assertResponseRedirects('/');
    }

    public function testLoginFailsWithAWrongPassword(): void
    {
        $client = static::createClient();
        $this->createVerifiedUser($client);

        $client->request('GET', '/login');
        $client->submitForm('login_submit', [
            '_username' => $this->email,
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');
    }

    public function testRepeatedFailedLoginsAreThrottled(): void
    {
        $client = static::createClient();
        $this->createVerifiedUser($client);

        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', '/login');
            $client->submitForm('login_submit', [
                '_username' => $this->email,
                '_password' => 'wrong-password',
            ]);
        }

        // 6th attempt: the firewall's login_throttling (max_attempts: 5) must reject it,
        // even though nothing about the submitted credentials changes.
        $client->request('GET', '/login');
        $client->submitForm('login_submit', [
            '_username' => $this->email,
            '_password' => 'wrong-password',
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-error', 'Too many failed login attempts');
    }

    private function createVerifiedUser(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        $this->email = sprintf('login-%s@example.com', uniqid());

        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User($this->email);
        $user->setPassword($passwordHasher->hashPassword($user, $this->password));
        $user->setVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $container->get(\App\Identity\Repository\UserRepository::class);

        foreach ($repository->findAll() as $user) {
            if (str_contains($user->getEmail(), '@example.com')) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
