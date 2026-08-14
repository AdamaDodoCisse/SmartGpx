<?php

declare(strict_types=1);

namespace App\Tests\Identity\Command;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteUserToAdminCommandTest extends KernelTestCase
{
    public function testItPromotesAnExistingUser(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('promote-cmd-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $tester = $this->commandTester();
        $exitCode = $tester->execute(['email' => $user->getEmail()]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('now has ROLE_ADMIN', $tester->getDisplay());

        $entityManager->refresh($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());

        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function testItFailsForAnUnknownEmail(): void
    {
        self::bootKernel();
        $tester = $this->commandTester();
        $exitCode = $tester->execute(['email' => 'does-not-exist@example.com']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('No user found', $tester->getDisplay());
    }

    /**
     * Ne (re)boote jamais le kernel elle-même — l'appelant doit déjà l'avoir fait, sans quoi
     * Symfony rebooterait un second kernel et détacherait toute entité persistée entre-temps.
     */
    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:user:promote-admin');

        return new CommandTester($command);
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
