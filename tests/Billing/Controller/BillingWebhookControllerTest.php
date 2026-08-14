<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Entity\CreditPack;
use App\Billing\Entity\CreditPurchase;
use App\Billing\Enum\WebhookEventType;
use App\Billing\Provider\BillingProviderInterface;
use App\Billing\Provider\FakeBillingProvider;
use App\Billing\Provider\StripeBillingProvider;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Billing\Result\WebhookEvent;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BillingWebhookControllerTest extends WebTestCase
{
    private const string WEBHOOK_SECRET = 'whsec_test_secret';

    public function testADuplicateWebhookDeliveryDoesNotDoubleGrantCredits(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createUserWithZeroBalance();
        $pack = $this->pack();
        $this->seedPendingPurchase($user, $pack, 'cs_test_webhook_dup');

        $fake = static::getContainer()->get(FakeBillingProvider::class);
        $fake->reset();
        $fake->queueWebhookEvent($this->webhookEventFor('cs_test_webhook_dup'));
        $fake->queueWebhookEvent($this->webhookEventFor('cs_test_webhook_dup'));

        $client->request('POST', '/billing/webhook/stripe', content: '{}');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/billing/webhook/stripe', content: '{}');
        self::assertResponseIsSuccessful();

        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        static::getContainer()->get(EntityManagerInterface::class)->refresh($account);
        self::assertSame($pack->getCredits(), $account->getBalance(), 'A duplicate delivery must not grant credits twice.');

        $transactions = static::getContainer()->get(CreditTransactionRepository::class)->findBy(['creditAccount' => $account]);
        self::assertCount(1, $transactions);

        $purchase = static::getContainer()->get(CreditPurchaseRepository::class)->findOneByStripeCheckoutSessionId('cs_test_webhook_dup');
        self::assertNotNull($purchase);
        self::assertTrue($purchase->isCompleted());
    }

    public function testAnUnknownCheckoutSessionIdIsAcknowledgedWithoutCrediting(): void
    {
        $client = static::createClient();

        $fake = static::getContainer()->get(FakeBillingProvider::class);
        $fake->reset();
        $fake->queueWebhookEvent($this->webhookEventFor('cs_test_does_not_exist'));

        $client->request('POST', '/billing/webhook/stripe', content: '{}');

        self::assertResponseIsSuccessful();
    }

    public function testAnUnhandledEventTypeIsAcknowledgedWithoutAction(): void
    {
        $client = static::createClient();

        $fake = static::getContainer()->get(FakeBillingProvider::class);
        $fake->reset();
        $fake->queueWebhookEvent(new WebhookEvent(WebhookEventType::UNHANDLED, checkoutSessionId: null, paymentIntentId: null, metadata: null));

        $client->request('POST', '/billing/webhook/stripe', content: '{}');

        self::assertResponseIsSuccessful();
    }

    public function testARealSignedPayloadIsAcceptedAndCreditsTheAccount(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithZeroBalance();
        $pack = $this->pack();
        $this->seedPendingPurchase($user, $pack, 'cs_test_real_signature');

        static::getContainer()->set(BillingProviderInterface::class, new StripeBillingProvider(
            new StripeClient('sk_test_fake'),
            self::WEBHOOK_SECRET,
            new NullLogger(),
        ));

        $payload = json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_real_signature', 'object' => 'checkout.session', 'payment_intent' => 'pi_test_1']],
        ], \JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        $client->request(
            'POST',
            '/billing/webhook/stripe',
            server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"],
            content: $payload,
        );

        self::assertResponseIsSuccessful();

        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        static::getContainer()->get(EntityManagerInterface::class)->refresh($account);
        self::assertSame($pack->getCredits(), $account->getBalance());
    }

    public function testABadSignatureIsRejected(): void
    {
        $client = static::createClient();

        static::getContainer()->set(BillingProviderInterface::class, new StripeBillingProvider(
            new StripeClient('sk_test_fake'),
            self::WEBHOOK_SECRET,
            new NullLogger(),
        ));

        $client->request('POST', '/billing/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => 't=1700000000,v1=deadbeef'], content: '{}');

        self::assertResponseStatusCodeSame(400);
    }

    public function testAMissingSignatureHeaderIsRejected(): void
    {
        $client = static::createClient();

        static::getContainer()->set(BillingProviderInterface::class, new StripeBillingProvider(
            new StripeClient('sk_test_fake'),
            self::WEBHOOK_SECRET,
            new NullLogger(),
        ));

        $client->request('POST', '/billing/webhook/stripe', content: '{}');

        self::assertResponseStatusCodeSame(400);
    }

    private function webhookEventFor(string $checkoutSessionId): WebhookEvent
    {
        return new WebhookEvent(WebhookEventType::CHECKOUT_SESSION_COMPLETED, checkoutSessionId: $checkoutSessionId, paymentIntentId: 'pi_test_fixture', metadata: null);
    }

    private function pack(): CreditPack
    {
        return static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[1]; // 100 credits
    }

    private function createUserWithZeroBalance(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('billing-webhook-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $user->setVerified(true);
        $entityManager->persist($user);

        $account = new CreditAccount($user);
        $account->initializeBalance(0);
        $entityManager->persist($account);
        $entityManager->flush();

        return $user;
    }

    private function seedPendingPurchase(User $user, CreditPack $pack, string $stripeCheckoutSessionId): CreditPurchase
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $purchase = new CreditPurchase($user, $pack, $stripeCheckoutSessionId);
        $entityManager->persist($purchase);
        $entityManager->flush();

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
