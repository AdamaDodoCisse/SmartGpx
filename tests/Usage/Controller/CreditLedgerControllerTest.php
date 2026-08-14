<?php

declare(strict_types=1);

namespace App\Tests\Usage\Controller;

use App\Identity\Entity\User;
use App\Usage\Entity\CreditAccount;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreditLedgerControllerTest extends WebTestCase
{
    public function testTheIndexPageShowsAnEmptyStateWithoutACreditAccount(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/account/credits');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "don't have any credit activity");
    }

    public function testTheIndexPageShowsTheBalanceAndLedger(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $account = $this->seedCredits($user, 30);

        $container = static::getContainer();
        $container->get(EntityManagerInterface::class)->persist(
            new CreditTransaction($account, CreditTransactionType::ADMIN_ADJUSTMENT, 25, 30),
        );
        $container->get(EntityManagerInterface::class)->flush();

        $client->loginUser($user);
        $client->request('GET', '/account/credits');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Adjustment');
        self::assertSelectorTextContains('body', '+25');
        self::assertSelectorTextContains('body', '30');
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/account/credits');

        self::assertResponseRedirects('/login');
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('credit-ledger-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function seedCredits(User $user, int $balance): CreditAccount
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $account = new CreditAccount($user);
        $account->initializeBalance($balance);
        $entityManager->persist($account);
        $entityManager->flush();

        return $account;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(CreditTransactionRepository::class)->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($container->get(CreditAccountRepository::class)->findAll() as $account) {
            $entityManager->remove($account);
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
