<?php

declare(strict_types=1);

namespace App\Tests\Usage\Action;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Action\GrantAdminCreditAdjustmentAction;
use App\Usage\Entity\CreditAccount;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GrantAdminCreditAdjustmentActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private GrantAdminCreditAdjustmentAction $action;
    private CreditAccountRepository $creditAccountRepository;
    private CreditTransactionRepository $creditTransactionRepository;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(GrantAdminCreditAdjustmentAction::class);
        $this->creditAccountRepository = $container->get(CreditAccountRepository::class);
        $this->creditTransactionRepository = $container->get(CreditTransactionRepository::class);

        $this->user = new User(sprintf('admin-adjustment-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);

        $account = new CreditAccount($this->user);
        $account->initializeBalance(3);
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    public function testItIncreasesBalanceAndWritesAnAdminAdjustmentLedgerRow(): void
    {
        $this->action->execute($this->user, 50);

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(53, $account->getBalance());

        $transactions = $this->creditTransactionRepository->findBy(['creditAccount' => $account]);
        self::assertCount(1, $transactions);
        self::assertSame(CreditTransactionType::ADMIN_ADJUSTMENT, $transactions[0]->getType());
        self::assertSame(50, $transactions[0]->getAmount());
        self::assertSame(53, $transactions[0]->getBalanceAfter());
    }

    public function testItRejectsAZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->action->execute($this->user, 0);
    }

    public function testItRejectsANegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->action->execute($this->user, -10);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($this->creditTransactionRepository->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($this->creditAccountRepository->findAll() as $account) {
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
