<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Controller;

use App\Conversion\Repository\ConversionRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Routing\Provider\FakeRoutingProvider;
use App\Routing\Provider\RoutingProviderInterface;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Le flux en deux temps "choisir son itinéraire" (options avancées : alternatives, route
 * économe en carburant) — voir PreviewGoogleMapsRoutesController/ExportPreviewedRouteController
 * et documentation/technique/routing-options.md. Couvre aussi ParseGoogleMapsUrlController et
 * RoutingCapabilitiesController, qui ne facturent jamais rien.
 */
final class RouteSelectionFlowTest extends WebTestCase
{
    private const string VALID_URL = 'https://www.google.com/maps/dir/?api=1&origin=Cergy%2C+France&destination=Paris%2C+France&travelmode=driving';

    public function testPreviewWithAlternativesReturnsCandidatesWithoutChargingACredit(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $routingProvider = static::getContainer()->get(RoutingProviderInterface::class);
        self::assertInstanceOf(FakeRoutingProvider::class, $routingProvider);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps/preview',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => self::VALID_URL, 'showAlternativeRoutes' => true, 'showFuelEfficientRoute' => true]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('previewId', $data);
        self::assertGreaterThanOrEqual(2, \count($data['candidates']));

        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        self::assertSame(1, $account->getBalance(), 'Previewing candidate routes must not reserve or spend a credit.');
        self::assertSame(0, $account->getReserved());
    }

    public function testExportingAPreviewedRouteChargesExactlyOneCreditAndAllowsDownload(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps/preview',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => self::VALID_URL, 'showAlternativeRoutes' => true]),
        );
        $previewData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($previewData);

        $client->request(
            'POST',
            '/api/conversions/google-maps/export',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['previewId' => $previewData['previewId'], 'selectedIndex' => 1]),
        );

        self::assertResponseIsSuccessful();
        $exportData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($exportData);
        self::assertArrayHasKey('downloadUrl', $exportData);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        $entityManager->refresh($account);
        self::assertSame(0, $account->getBalance());
        self::assertSame(0, $account->getReserved());

        $client->request('GET', $exportData['downloadUrl']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<gpx', (string) $client->getResponse()->getContent());
    }

    public function testExportingAnUnknownPreviewReturnsGone(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps/export',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['previewId' => 'does-not-exist', 'selectedIndex' => 0]),
        );

        self::assertSame(410, $client->getResponse()->getStatusCode());

        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        self::assertSame(1, $account->getBalance(), 'A missing preview must not reserve a credit.');
    }

    public function testExportingAnOutOfRangeIndexReturnsUnprocessableEntity(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps/preview',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => self::VALID_URL, 'showAlternativeRoutes' => true]),
        );
        $previewData = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($previewData);

        $client->request(
            'POST',
            '/api/conversions/google-maps/export',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['previewId' => $previewData['previewId'], 'selectedIndex' => 99]),
        );

        self::assertSame(422, $client->getResponse()->getStatusCode());

        $account = static::getContainer()->get(CreditAccountRepository::class)->findOneByUserOrFail($user);
        self::assertSame(1, $account->getBalance());
    }

    public function testParseIsAccessibleAnonymouslyAndReturnsStops(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/conversions/google-maps/parse',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::jsonBody(['url' => self::VALID_URL]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('Cergy, France', $data['origin']);
        self::assertSame('Paris, France', $data['destination']);
        self::assertSame([], $data['stops']);
    }

    public function testParseOfAnUnsupportedUrlReturnsUnprocessableEntity(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/conversions/google-maps/parse',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::jsonBody(['url' => 'https://example.com/not-google-maps']),
        );

        self::assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testCapabilitiesAreAccessibleAnonymously(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/routing/capabilities');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('maxIntermediateWaypoints', $data);
        self::assertArrayHasKey('supportedTravelModes', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function jsonBody(array $data): string
    {
        $json = json_encode($data);

        if (false === $json) {
            throw new \RuntimeException('Failed to encode test payload as JSON.');
        }

        return $json;
    }

    private function extractCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $value = $crawler->filter('#convert-hero-root')->attr('data-csrf-token');
        self::assertIsString($value);

        return $value;
    }

    private function createVerifiedUser(): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('route-selection-%s@example.com', uniqid()));
        $user->setPassword($passwordHasher->hashPassword($user, 'correct-horse-battery-staple'));
        $user->setVerified(true);

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

        foreach ($container->get(ConversionRepository::class)->findAll() as $conversion) {
            $entityManager->remove($conversion);
        }
        $entityManager->flush();

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
