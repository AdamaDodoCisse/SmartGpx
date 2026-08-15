<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Entity\CreditPack;
use App\Billing\Entity\CreditPurchase;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BillingCheckoutStatusControllerTest extends WebTestCase
{
    public function testConfirmingAnalyticsForACompletedPurchaseClaimsOnlyOnce(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $pack = $this->pack();
        $purchase = $this->seedCompletedPurchase($user, $pack, 'cs_test_confirm_once');
        $client->loginUser($user);

        $token = $this->csrfToken($client, $purchase);

        $client->request('POST', "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics", server: $this->csrfHeaders($token), content: '{}');
        self::assertResponseIsSuccessful();
        $first = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('completed', $first['status']);
        self::assertTrue($first['claimed']);
        self::assertNotNull($first['analytics']);
        self::assertSame($pack->getCredits(), $first['analytics']['credits']);

        $client->request('POST', "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics", server: $this->csrfHeaders($token), content: '{}');
        self::assertResponseIsSuccessful();
        $second = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('completed', $second['status']);
        self::assertFalse($second['claimed'], 'A second call must not double-claim the analytics event.');
        self::assertNotNull($second['analytics'], 'A revisit must still be able to render the success state.');
        self::assertSame($first['analytics']['transactionId'], $second['analytics']['transactionId']);
    }

    public function testConfirmingAnalyticsForAPendingPurchaseDoesNotClaim(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $pack = $this->pack();
        $purchase = $this->seedPendingPurchase($user, $pack, 'cs_test_confirm_pending');
        $client->loginUser($user);

        $token = $this->csrfToken($client, $purchase);

        $client->request('POST', "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics", server: $this->csrfHeaders($token), content: '{}');
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('pending', $data['status']);
        self::assertFalse($data['claimed']);
        self::assertNull($data['analytics']);
    }

    public function testConfirmingAnalyticsForAnotherUsersPurchaseReturnsNotFound(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $intruder = $this->createUser();
        $pack = $this->pack();
        $purchase = $this->seedCompletedPurchase($owner, $pack, 'cs_test_confirm_intruder');
        $client->loginUser($owner);
        $token = $this->csrfToken($client, $purchase);

        $client->loginUser($intruder);
        $client->request('POST', "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics", server: $this->csrfHeaders($token), content: '{}');

        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmingAnalyticsForAnUnknownPublicIdReturnsNotFound(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $pack = $this->pack();
        // Uniquement pour obtenir un jeton CSRF valide (l'intention n'est pas liée à un achat
        // précis) : le vrai objet de ce test est un publicId inconnu dans l'URL.
        $throwawayPurchase = $this->seedCompletedPurchase($user, $pack, 'cs_test_confirm_unknown_token_source');
        $client->loginUser($user);
        $token = $this->csrfToken($client, $throwawayPurchase);

        $client->request(
            'POST',
            '/api/billing/checkout/'.new \Symfony\Component\Uid\UuidV7().'/confirm-analytics',
            server: $this->csrfHeaders($token),
            content: '{}',
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $pack = $this->pack();
        $purchase = $this->seedCompletedPurchase($user, $pack, 'cs_test_confirm_bad_csrf');
        $client->loginUser($user);

        $client->request(
            'POST',
            "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics",
            server: $this->csrfHeaders('not-a-valid-token'),
            content: '{}',
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function testTheAnalyticsPayloadContainsOnlyAllowlistedEcommerceFields(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $pack = $this->pack();
        $purchase = $this->seedCompletedPurchase($user, $pack, 'cs_test_confirm_payload_shape');
        $client->loginUser($user);
        $token = $this->csrfToken($client, $purchase);

        $client->request('POST', "/api/billing/checkout/{$purchase->getPublicId()}/confirm-analytics", server: $this->csrfHeaders($token), content: '{}');
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(
            ['transactionId', 'value', 'currency', 'credits', 'itemId', 'itemName'],
            array_keys($data['analytics']),
        );

        $serialized = strtolower((string) $client->getResponse()->getContent());
        foreach (['email', 'card', 'stripe_secret', 'billing_address'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    /**
     * @return array<string, string>
     */
    private function csrfHeaders(string $token): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => $token,
        ];
    }

    /**
     * Lu depuis data-csrf-token sur le point de montage du script de la page de succès — même
     * convention que ConvertGoogleMapsControllerTest::extractCsrfToken() pour #convert-hero-root.
     */
    private function csrfToken(KernelBrowser $client, CreditPurchase $purchase): string
    {
        $crawler = $client->request('GET', '/billing/checkout/success?session_id='.$purchase->getStripeCheckoutSessionId());
        $value = $crawler->filter('#billing-checkout-success-root')->attr('data-csrf-token');
        self::assertIsString($value);

        return $value;
    }

    private function pack(): CreditPack
    {
        return static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[1]; // 100 credits
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('billing-analytics-%s@example.com', uniqid()));
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

    private function seedCompletedPurchase(User $user, CreditPack $pack, string $stripeCheckoutSessionId): CreditPurchase
    {
        $purchase = $this->seedPendingPurchase($user, $pack, $stripeCheckoutSessionId);
        $purchase->markCompleted();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        return $purchase;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

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
