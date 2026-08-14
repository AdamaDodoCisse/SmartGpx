<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * `_preview_error` (Symfony/TwigBundle's `/_error/{code}.{_format}` route) only exists `when@dev`
 * (config/routes/framework.yaml) — it's absent in the `test` environment, so these tests trigger
 * the real conditions instead. createClient(['debug' => false]) forces the kernel to render the
 * actual production error page rather than the dev debug exception page.
 */
final class ErrorPagesTest extends WebTestCase
{
    public function testANonExistentPageRendersTheBranded404Template(): void
    {
        $client = static::createClient(['debug' => false]);

        $client->request('GET', '/this-page-does-not-exist');

        self::assertResponseStatusCodeSame(404);
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Page not found', $content);
        self::assertStringContainsString('Back to homepage', $content);
        self::assertStringNotContainsString('error.', $content);
    }

    public function testAForbiddenAdminRouteRendersTheBrandedGenericTemplate(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->loginUser($this->createRegularUser());

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Something went wrong', $content);
        self::assertStringContainsString('Back to homepage', $content);
        self::assertStringNotContainsString('error.', $content);
    }

    private function createRegularUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('error-pages-plain-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
