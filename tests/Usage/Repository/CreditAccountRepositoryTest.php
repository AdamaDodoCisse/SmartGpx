<?php

declare(strict_types=1);

namespace App\Tests\Usage\Repository;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditAccountRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CreditAccountRepository $creditAccountRepository;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->creditAccountRepository = $container->get(CreditAccountRepository::class);

        $this->user = new User(sprintf('credit-repo-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testReserveOneSucceedsOnlyWhileBalanceIsAvailable(): void
    {
        $this->seedAccount(balance: 1, reserved: 0);

        self::assertTrue($this->creditAccountRepository->reserveOne($this->user));
        self::assertFalse($this->creditAccountRepository->reserveOne($this->user));

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(0, $account->getBalance());
        self::assertSame(1, $account->getReserved());
    }

    public function testReserveThenConsumeKeepsTheInvariant(): void
    {
        $this->seedAccount(balance: 3, reserved: 0);

        self::assertTrue($this->creditAccountRepository->reserveOne($this->user));
        $balanceAfter = $this->creditAccountRepository->decrementReservedAndGetBalance($this->user);

        self::assertSame(2, $balanceAfter);

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(2, $account->getBalance());
        self::assertSame(0, $account->getReserved());
    }

    public function testReserveThenReleaseRestoresTheBalance(): void
    {
        $this->seedAccount(balance: 1, reserved: 0);

        self::assertTrue($this->creditAccountRepository->reserveOne($this->user));
        $this->creditAccountRepository->releaseOneReservation($this->user);

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(1, $account->getBalance());
        self::assertSame(0, $account->getReserved());
    }

    public function testReservingWithZeroBalanceFails(): void
    {
        $this->seedAccount(balance: 0, reserved: 0);

        self::assertFalse($this->creditAccountRepository->reserveOne($this->user));
    }

    private function seedAccount(int $balance, int $reserved): void
    {
        $account = new CreditAccount($this->user);
        $account->initializeBalance($balance);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        if ($reserved > 0) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE credit_account SET reserved = :reserved WHERE user_id = :userId',
                ['reserved' => $reserved, 'userId' => $this->user->getId()],
            );
        }
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $creditTransactionRepository = $container->get(CreditTransactionRepository::class);
        $userRepository = $container->get(UserRepository::class);

        foreach ($creditTransactionRepository->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($this->creditAccountRepository->findAll() as $account) {
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
