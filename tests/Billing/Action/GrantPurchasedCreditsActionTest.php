<?php

declare(strict_types=1);

namespace App\Tests\Billing\Action;

use App\Billing\Action\GrantPurchasedCreditsAction;
use App\Billing\Entity\CreditPack;
use App\Billing\Entity\CreditPurchase;
use App\Billing\Exception\CreditPurchaseNotFoundException;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GrantPurchasedCreditsActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private GrantPurchasedCreditsAction $action;
    private CreditAccountRepository $creditAccountRepository;
    private CreditPack $pack;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(GrantPurchasedCreditsAction::class);
        $this->creditAccountRepository = $container->get(CreditAccountRepository::class);
        $this->pack = $container->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[1]; // 100 credits

        $this->user = new User(sprintf('grant-credits-action-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);

        $account = new CreditAccount($this->user);
        $account->initializeBalance(0);
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    public function testItGrantsCreditsAndMarksThePurchaseCompleted(): void
    {
        $purchase = $this->seedPendingPurchase('cs_test_grant_1');

        $this->action->execute('cs_test_grant_1');

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame($this->pack->getCredits(), $account->getBalance());

        $this->entityManager->refresh($purchase);
        self::assertTrue($purchase->isCompleted());

        $creditTransactionRepository = static::getContainer()->get(CreditTransactionRepository::class);
        $transactions = $creditTransactionRepository->findBy(['creditAccount' => $account]);
        self::assertCount(1, $transactions);
        self::assertSame(CreditTransactionType::PURCHASE, $transactions[0]->getType());
        self::assertSame($this->pack->getCredits(), $transactions[0]->getAmount());
        self::assertSame($purchase->getId(), $transactions[0]->getCreditPurchaseId());
    }

    public function testADuplicateWebhookDeliveryDoesNotDoubleGrantCredits(): void
    {
        $this->seedPendingPurchase('cs_test_grant_2');

        $this->action->execute('cs_test_grant_2');
        $this->action->execute('cs_test_grant_2');

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame($this->pack->getCredits(), $account->getBalance(), 'A duplicate delivery must not grant credits twice.');

        $creditTransactionRepository = static::getContainer()->get(CreditTransactionRepository::class);
        self::assertCount(1, $creditTransactionRepository->findBy(['creditAccount' => $account]));
    }

    public function testAnUnknownCheckoutSessionIdThrows(): void
    {
        $this->expectException(CreditPurchaseNotFoundException::class);
        $this->action->execute('cs_test_does_not_exist');
    }

    private function seedPendingPurchase(string $stripeCheckoutSessionId): CreditPurchase
    {
        $purchase = new CreditPurchase($this->user, $this->pack, $stripeCheckoutSessionId);
        $this->entityManager->persist($purchase);
        $this->entityManager->flush();

        return $purchase;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(CreditTransactionRepository::class)->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($container->get(CreditPurchaseRepository::class)->findAll() as $purchase) {
            $entityManager->remove($purchase);
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
