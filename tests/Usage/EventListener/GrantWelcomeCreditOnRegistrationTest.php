<?php

declare(strict_types=1);

namespace App\Tests\Usage\EventListener;

use App\Identity\Action\RegisterUserAction;
use App\Identity\Repository\UserRepository;
use App\Identity\Request\RegisterUserRequest;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GrantWelcomeCreditOnRegistrationTest extends KernelTestCase
{
    public function testRegisteringAUserGrantsOneWelcomeCredit(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $registerUserAction = $container->get(RegisterUserAction::class);
        $creditAccountRepository = $container->get(CreditAccountRepository::class);
        $creditTransactionRepository = $container->get(CreditTransactionRepository::class);

        $request = new RegisterUserRequest();
        $request->email = sprintf('welcome-credit-%s@example.com', uniqid());
        $request->plainPassword = 'correct-horse-battery-staple';

        $user = $registerUserAction->execute($request);

        $account = $creditAccountRepository->findOneByUser($user);
        self::assertNotNull($account);
        self::assertSame(1, $account->getBalance());
        self::assertSame(0, $account->getReserved());

        $transactions = $creditTransactionRepository->findBy(['creditAccount' => $account]);
        self::assertCount(1, $transactions);
        /** @var CreditTransaction $transaction */
        $transaction = $transactions[0];
        self::assertSame(CreditTransactionType::WELCOME, $transaction->getType());
        self::assertSame(1, $transaction->getAmount());
        self::assertSame(1, $transaction->getBalanceAfter());
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $creditTransactionRepository = $container->get(CreditTransactionRepository::class);
        $creditAccountRepository = $container->get(CreditAccountRepository::class);
        $userRepository = $container->get(UserRepository::class);

        foreach ($creditTransactionRepository->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($creditAccountRepository->findAll() as $account) {
            $entityManager->remove($account);
        }
        $entityManager->flush();

        foreach ($userRepository->findAll() as $user) {
            if (str_contains($user->getEmail(), '@example.com')) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
