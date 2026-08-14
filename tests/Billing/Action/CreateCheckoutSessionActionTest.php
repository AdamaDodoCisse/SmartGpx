<?php

declare(strict_types=1);

namespace App\Tests\Billing\Action;

use App\Billing\Action\CreateCheckoutSessionAction;
use App\Billing\Entity\CreditPack;
use App\Billing\Enum\CreditPurchaseStatus;
use App\Billing\Provider\FakeBillingProvider;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Billing\Result\CheckoutSession;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateCheckoutSessionActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CreateCheckoutSessionAction $action;
    private FakeBillingProvider $fakeBillingProvider;
    private CreditPack $pack;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(CreateCheckoutSessionAction::class);
        $this->fakeBillingProvider = $container->get(FakeBillingProvider::class);
        $this->fakeBillingProvider->reset();
        $this->pack = $container->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];

        $this->user = new User(sprintf('checkout-action-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testItCreatesAPendingPurchaseReferencingTheRealStripeSessionId(): void
    {
        $this->fakeBillingProvider->queue(new CheckoutSession(id: 'cs_test_specific', redirectUrl: 'https://checkout.stripe.com/c/pay/cs_test_specific'));

        $session = $this->action->execute($this->user, $this->pack, 'https://smartgpx.test/success', 'https://smartgpx.test/cancel');

        self::assertSame('cs_test_specific', $session->id);
        self::assertSame(1, $this->fakeBillingProvider->checkoutCallCount);

        $purchase = static::getContainer()->get(CreditPurchaseRepository::class)->findOneByStripeCheckoutSessionId('cs_test_specific');
        self::assertNotNull($purchase);
        self::assertSame($this->user->getId(), $purchase->getUser()->getId());
        self::assertSame($this->pack->getCredits(), $purchase->getCredits());
        self::assertSame($this->pack->getPriceCents(), $purchase->getAmountCents());
        self::assertSame(CreditPurchaseStatus::PENDING, $purchase->getStatus());
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(CreditPurchaseRepository::class)->findAll() as $purchase) {
            $entityManager->remove($purchase);
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
