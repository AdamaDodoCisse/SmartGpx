<?php

declare(strict_types=1);

namespace App\Tests\Extension\Controller;

use App\Extension\Action\GenerateExtensionAuthorizationAction;
use App\Extension\Action\RevokeExtensionAuthorizationAction;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Usage\Entity\CreditAccount;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExtensionConversionControllerTest extends WebTestCase
{
    private const string VALID_URL = 'https://www.google.com/maps/dir/?api=1&origin=Cergy%2C+France&destination=Paris%2C+France&travelmode=driving';

    public function testMissingAuthorizationHeaderIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/extension/account');

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testMalformedAuthorizationHeaderIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/extension/account', server: ['HTTP_AUTHORIZATION' => 'Basic not-a-bearer-token']);

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testUnknownTokenIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/extension/account', server: ['HTTP_AUTHORIZATION' => 'Bearer sgpx_ext_doesnotexist']);

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testValidTokenSucceedsAndReportsAccountState(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->seedCredits($user, 1);
        $token = $this->generateToken($user);

        $client->request('GET', '/api/extension/account', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(1, $data['creditBalance']);
        self::assertFalse($data['hasEverConverted']);
    }

    public function testConvertAndDownloadWithATokenNeedsNoCsrfHeader(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->seedCredits($user, 1);
        $token = $this->generateToken($user);

        $client->request(
            'POST',
            '/api/extension/conversions/google-maps',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            content: self::jsonBody(['url' => self::VALID_URL]),
        );

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Cergy, France', $data['origin']);
        self::assertArrayHasKey('downloadUrl', $data);

        $client->request('GET', $data['downloadUrl'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<gpx', (string) $client->getResponse()->getContent());
    }

    public function testARevokedTokenStopsWorkingImmediately(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->seedCredits($user, 2);
        $token = $this->generateToken($user);

        // Le jeton fonctionne d'abord normalement.
        $client->request('GET', '/api/extension/account', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();

        $this->revokeAllFor($user);

        // Le même jeton, littéralement identique, échoue immédiatement — aucune mise en cache.
        $client->request('GET', '/api/extension/account', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    private function createUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('ext-conv-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
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

    private function generateToken(User $user): string
    {
        $container = static::getContainer();

        return $container->get(GenerateExtensionAuthorizationAction::class)->execute($user, 'Test browser')->plainToken;
    }

    private function revokeAllFor(User $user): void
    {
        $container = static::getContainer();
        $repository = $container->get(ExtensionAuthorizationRepository::class);
        $revokeAction = $container->get(RevokeExtensionAuthorizationAction::class);

        foreach ($repository->findAllForUser($user) as $authorization) {
            $revokeAction->execute($user, (string) $authorization->getPublicId());
        }
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

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(\App\Conversion\Repository\ConversionRepository::class)->findAll() as $conversion) {
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

        foreach ($container->get(ExtensionAuthorizationRepository::class)->findAll() as $authorization) {
            $entityManager->remove($authorization);
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
