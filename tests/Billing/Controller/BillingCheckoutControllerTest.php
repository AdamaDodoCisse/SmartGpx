<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Entity\CreditPack;
use App\Billing\Exception\BillingProviderUnavailableException;
use App\Billing\Provider\FakeBillingProvider;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Billing\Result\CheckoutSession;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\UuidV7;

final class BillingCheckoutControllerTest extends WebTestCase
{
    public function testCreatingACheckoutSessionRedirectsToStripeAndCreatesAPendingPurchase(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createUser();
        $client->loginUser($user);

        static::getContainer()->get(FakeBillingProvider::class)->reset();
        static::getContainer()->get(FakeBillingProvider::class)->queue(
            new CheckoutSession(id: 'cs_test_checkout_ctrl', redirectUrl: 'https://checkout.stripe.com/c/pay/cs_test_checkout_ctrl'),
        );

        $pack = static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];
        $crawler = $client->request('GET', '/pricing');
        $token = $this->extractCsrfToken($crawler, $pack);

        $client->request('POST', "/billing/checkout/{$pack->getPublicId()}", ['_token' => $token]);

        self::assertResponseRedirects('https://checkout.stripe.com/c/pay/cs_test_checkout_ctrl');

        $purchase = static::getContainer()->get(CreditPurchaseRepository::class)->findOneByStripeCheckoutSessionId('cs_test_checkout_ctrl');
        self::assertNotNull($purchase);
        self::assertSame($user->getId(), $purchase->getUser()->getId());
    }

    public function testAnUnknownPackReturnsNotFound(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $pack = static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];
        $crawler = $client->request('GET', '/pricing');
        $token = $this->extractCsrfToken($crawler, $pack);

        $client->request('POST', '/billing/checkout/'.new UuidV7(), ['_token' => $token]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $pack = static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];

        $client->request('POST', "/billing/checkout/{$pack->getPublicId()}", ['_token' => 'not-a-valid-token']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $pack = static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];

        $client->request('POST', "/billing/checkout/{$pack->getPublicId()}", ['_token' => 'irrelevant']);

        self::assertResponseRedirects('/login');
    }

    public function testABillingProviderFailureRedirectsToPricingWithAFlash(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createUser();
        $client->loginUser($user);

        static::getContainer()->get(FakeBillingProvider::class)->reset();
        static::getContainer()->get(FakeBillingProvider::class)->queue(
            new BillingProviderUnavailableException('Simulated outage.'),
        );

        $pack = static::getContainer()->get(CreditPackRepository::class)->findActiveOrderedForDisplay()[0];
        $crawler = $client->request('GET', '/pricing');
        $token = $this->extractCsrfToken($crawler, $pack);

        $client->request('POST', "/billing/checkout/{$pack->getPublicId()}", ['_token' => $token]);

        self::assertResponseRedirects('/pricing');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'payment provider');
    }

    private function extractCsrfToken(Crawler $crawler, CreditPack $pack): string
    {
        $token = $crawler->filter('form[action$="/'.$pack->getPublicId().'"] input[name="_token"]')->attr('value');
        self::assertIsString($token);

        return $token;
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('billing-checkout-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $user->setVerified(true);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
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
