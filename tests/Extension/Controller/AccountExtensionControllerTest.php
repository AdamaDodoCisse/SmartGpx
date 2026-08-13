<?php

declare(strict_types=1);

namespace App\Tests\Extension\Controller;

use App\Extension\Action\GenerateExtensionAuthorizationAction;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountExtensionControllerTest extends WebTestCase
{
    public function testTheIndexPageListsAuthorizationsAndAllowsConnecting(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/account/extensions');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "haven't connected");

        $client->submitForm('connect_submit');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('extension-connect-root', (string) $client->getResponse()->getContent());

        $repository = static::getContainer()->get(ExtensionAuthorizationRepository::class);
        self::assertCount(1, $repository->findAllForUser($user));

        unset($crawler);
    }

    public function testRevokingAnAuthorizationKeepsItListedButMarkedRevoked(): void
    {
        $client = static::createClient();
        $user = $this->createVerifiedUser();
        $client->loginUser($user);

        $generated = static::getContainer()->get(GenerateExtensionAuthorizationAction::class)->execute($user, 'Test browser');
        $publicId = (string) $generated->authorization->getPublicId();

        $crawler = $client->request('GET', '/account/extensions');
        $token = $crawler->filter('form[action$="/'.$publicId.'/revoke"] input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', "/account/extensions/{$publicId}/revoke", ['_token' => $token]);
        self::assertResponseRedirects('/account/extensions');
        $client->followRedirect();

        self::assertResponseIsSuccessful();

        $repository = static::getContainer()->get(ExtensionAuthorizationRepository::class);
        $authorizations = $repository->findAllForUser($user);
        self::assertCount(1, $authorizations, 'A revoked authorization must stay listed, never deleted.');
        self::assertTrue($authorizations[0]->isRevoked());
    }

    public function testRevokingAnotherUsersAuthorizationReturnsNotFound(): void
    {
        $client = static::createClient();
        $owner = $this->createVerifiedUser();
        $intruder = $this->createVerifiedUser();

        $ownerGenerated = static::getContainer()->get(GenerateExtensionAuthorizationAction::class)->execute($owner);
        $ownerPublicId = (string) $ownerGenerated->authorization->getPublicId();

        static::getContainer()->get(GenerateExtensionAuthorizationAction::class)->execute($intruder, 'Intruder browser');

        $client->loginUser($intruder);
        $crawler = $client->request('GET', '/account/extensions');
        $token = $crawler->filter('form[action$="/revoke"] input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', "/account/extensions/{$ownerPublicId}/revoke", ['_token' => $token]);

        self::assertResponseStatusCodeSame(404);
    }

    private function createVerifiedUser(): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('account-ext-%s@example.com', uniqid()));
        $user->setPassword($passwordHasher->hashPassword($user, 'correct-horse-battery-staple'));
        $user->setVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

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
