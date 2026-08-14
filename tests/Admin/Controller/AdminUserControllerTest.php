<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminUserControllerTest extends WebTestCase
{
    public function testTheIndexListsUsers(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        $client->loginUser($admin);

        $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $admin->getEmail());
    }

    public function testTheShowPageDisplaysTheLedgerAndAllowsGrantingCredits(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        $client->loginUser($admin);

        $target = $this->createRegularUser();
        $this->seedCredits($target, 5);

        $crawler = $client->request('GET', '/admin/users/'.$target->getPublicId());
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/credit-adjustment"]')->form();
        $form['credit_adjustment_form[amount]'] = '25';
        $client->submit($form);

        self::assertResponseRedirects('/admin/users/'.$target->getPublicId());
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'admin adjustment');

        $creditAccountRepository = static::getContainer()->get(CreditAccountRepository::class);
        $account = $creditAccountRepository->findOneByUserOrFail($target);
        self::assertSame(30, $account->getBalance());
    }

    public function testGrantingAZeroAmountIsRejectedAndDoesNotChangeTheBalance(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        $client->loginUser($admin);

        $target = $this->createRegularUser();
        $this->seedCredits($target, 5);

        $crawler = $client->request('GET', '/admin/users/'.$target->getPublicId());
        $form = $crawler->filter('form[action$="/credit-adjustment"]')->form();
        $form['credit_adjustment_form[amount]'] = '0';
        $client->submit($form);

        self::assertResponseIsSuccessful();

        $creditAccountRepository = static::getContainer()->get(CreditAccountRepository::class);
        $account = $creditAccountRepository->findOneByUserOrFail($target);
        self::assertSame(5, $account->getBalance());
    }

    public function testCreditAdjustmentWithoutACsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser();
        $client->loginUser($admin);

        $target = $this->createRegularUser();
        $this->seedCredits($target, 5);

        $client->request('POST', '/admin/users/'.$target->getPublicId().'/credit-adjustment', [
            'credit_adjustment_form' => ['amount' => 25],
        ]);

        self::assertResponseIsSuccessful();

        $creditAccountRepository = static::getContainer()->get(CreditAccountRepository::class);
        $account = $creditAccountRepository->findOneByUserOrFail($target);
        self::assertSame(5, $account->getBalance(), 'A request without the form\'s real CSRF token must not grant credits.');
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-users-%s@example.com', uniqid()));
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

        $user = new User(sprintf('admin-users-target-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function seedCredits(User $user, int $balance): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $account = new CreditAccount($user);
        $account->initializeBalance($balance);
        $entityManager->persist($account);
        $entityManager->flush();
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
