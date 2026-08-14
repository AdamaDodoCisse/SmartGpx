<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Billing\Entity\CreditPurchase;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminPurchaseControllerTest extends WebTestCase
{
    public function testTheIndexListsPurchasesWithStatusAndAmount(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $buyer = $this->createRegularUser();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $pack = $container->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];

        $purchase = new CreditPurchase($buyer, $pack, 'cs_test_admin_purchases_'.uniqid());
        $purchase->markCompleted();
        $entityManager->persist($purchase);
        $entityManager->flush();

        $client->request('GET', '/admin/purchases');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $buyer->getEmail());
        self::assertSelectorTextContains('body', 'completed');

        $entityManager->remove($purchase);
        $entityManager->flush();
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-purchases-%s@example.com', uniqid()));
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

        $user = new User(sprintf('admin-purchases-buyer-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(CreditPurchaseRepository::class)->findAll() as $purchase) {
            if (str_contains($purchase->getStripeCheckoutSessionId(), 'cs_test_admin_purchases_')) {
                $entityManager->remove($purchase);
            }
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
