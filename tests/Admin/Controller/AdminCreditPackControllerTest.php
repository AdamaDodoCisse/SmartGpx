<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Billing\Entity\CreditPack;
use App\Billing\Repository\CreditPackRepository;
use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

final class AdminCreditPackControllerTest extends WebTestCase
{
    public function testTheIndexListsInactivePacksToo(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $inactive = new CreditPack(777, 77700, 'usd', null, 777, false);
        $entityManager->persist($inactive);
        $entityManager->flush();

        $client->request('GET', '/admin/credit-packs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '777');
    }

    public function testCreatingAPackMakesItAppearOnThePublicPricingPage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $crawler = $client->request('GET', '/admin/credit-packs/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['credit_pack_form[credits]'] = '12345';
        $form['credit_pack_form[priceCents]'] = '999';
        $form['credit_pack_form[currency]'] = 'usd';
        $form['credit_pack_form[displayOrder]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects('/admin/credit-packs');

        $client->request('GET', '/pricing');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '12345');
    }

    public function testEditingAPackUpdatesItAndDeactivatingRemovesItFromPricing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $pack = new CreditPack(54321, 5432, 'usd', null, 1, true);
        $entityManager->persist($pack);
        $entityManager->flush();

        $crawler = $client->request('GET', '/admin/credit-packs/'.$pack->getPublicId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['credit_pack_form[credits]'] = '54321';
        $form['credit_pack_form[priceCents]'] = '5432';
        $form['credit_pack_form[currency]'] = 'usd';
        $form['credit_pack_form[displayOrder]'] = '1';
        $activeField = $form->get('credit_pack_form[active]');
        self::assertInstanceOf(ChoiceFormField::class, $activeField);
        $activeField->untick();
        $client->submit($form);

        self::assertResponseRedirects('/admin/credit-packs');

        $client->request('GET', '/pricing');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('54321', (string) $client->getResponse()->getContent());
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-credit-packs-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $container->get(PromoteUserToAdminAction::class)->execute($user);

        return $user;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Nettoyage centralisé dans tearDown (jamais dans le corps des tests) : tearDown()
        // s'exécute même quand un test échoue en cours de route, contrairement à un removal en
        // fin de méthode — sinon un pack de test orphelin (encore actif) fausse silencieusement
        // les assertions de tous les runs suivants sur /pricing.
        $creditPackRepository = $container->get(CreditPackRepository::class);
        foreach ([12345, 54321, 777] as $testCredits) {
            foreach ($creditPackRepository->findBy(['credits' => $testCredits]) as $pack) {
                $entityManager->remove($pack);
            }
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
