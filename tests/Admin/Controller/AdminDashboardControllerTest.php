<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminDashboardControllerTest extends WebTestCase
{
    public function testTheDashboardRendersMetricsForAnAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Total users');
    }

    #[DataProvider('adminRoutes')]
    public function testAPlainUserGetsForbidden(string $path): void
    {
        $client = static::createClient();
        $client->loginUser($this->createRegularUser());

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('adminRoutes')]
    public function testAnAnonymousVisitorIsRedirectedToLogin(string $path): void
    {
        $client = static::createClient();

        $client->request('GET', $path);

        self::assertResponseRedirects();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminRoutes(): iterable
    {
        yield 'dashboard' => ['/admin'];
        yield 'users' => ['/admin/users'];
        yield 'purchases' => ['/admin/purchases'];
        yield 'credit packs' => ['/admin/credit-packs'];
        yield 'failed conversions' => ['/admin/conversions/failed'];
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-dashboard-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $container->get(PromoteUserToAdminAction::class)->execute($user);

        return $user;
    }

    private function createRegularUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('admin-dashboard-plain-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

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
