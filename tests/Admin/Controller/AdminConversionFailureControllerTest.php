<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Conversion\Entity\ConversionFailure;
use App\Conversion\Enum\ConversionFailureReason;
use App\Conversion\Repository\ConversionFailureRepository;
use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminConversionFailureControllerTest extends WebTestCase
{
    public function testTheIndexListsFailuresWithReasonAndUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $target = $this->createRegularUser();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $failure = new ConversionFailure($target, 'https://example.com/not-google-maps', ConversionFailureReason::UNSUPPORTED_URL);
        $entityManager->persist($failure);
        $entityManager->flush();

        $client->request('GET', '/admin/conversions/failed');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $target->getEmail());
        self::assertSelectorTextContains('body', 'unsupported_url');

        $entityManager->remove($failure);
        $entityManager->flush();
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-failed-conversions-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $container->get(PromoteUserToAdminAction::class)->execute($user);

        return $user;
    }

    private function createRegularUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-failed-conversions-target-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(ConversionFailureRepository::class)->findAll() as $failure) {
            $entityManager->remove($failure);
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
