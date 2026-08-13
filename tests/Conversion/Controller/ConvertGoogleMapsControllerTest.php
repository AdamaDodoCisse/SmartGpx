<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Controller;

use App\Conversion\Repository\ConversionRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ConvertGoogleMapsControllerTest extends WebTestCase
{
    private const string VALID_URL = 'https://www.google.com/maps/dir/?api=1&origin=Cergy%2C+France&destination=Paris%2C+France&travelmode=driving';

    public function testAuthenticatedUserWithCreditsCanConvertAndDownload(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => self::VALID_URL]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('Cergy, France', $data['origin']);
        self::assertSame('Paris, France', $data['destination']);
        self::assertArrayHasKey('downloadUrl', $data);

        $client->request('GET', $data['downloadUrl']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('gpx', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('<gpx', (string) $client->getResponse()->getContent());
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/conversions/google-maps',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::jsonBody(['url' => self::VALID_URL]),
        );

        self::assertContains($client->getResponse()->getStatusCode(), [401, 302, 403]);
    }

    public function testZeroBalanceReturnsPaymentRequired(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 0);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => self::VALID_URL]),
        );

        self::assertSame(402, $client->getResponse()->getStatusCode());
    }

    public function testInvalidUrlReturnsUnprocessableEntity(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $this->seedCredits($user, 1);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/');
        $token = $this->extractCsrfToken($crawler);

        $client->request(
            'POST',
            '/api/conversions/google-maps',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: self::jsonBody(['url' => 'not-a-url']),
        );

        self::assertSame(422, $client->getResponse()->getStatusCode());
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
        // Lu depuis data-csrf-token sur le point de montage de l'îlot ConvertHero
        // (voir templates/home/index.html.twig) — exactement ce que fait ce composant React.
        $value = $crawler->filter('#convert-hero-root')->attr('data-csrf-token');
        self::assertIsString($value);

        return $value;
    }

    private function createVerifiedUser(): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('convert-controller-%s@example.com', uniqid()));
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
