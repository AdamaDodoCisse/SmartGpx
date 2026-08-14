<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Régression : base.html.twig déduisait autrefois le lien canonical/hreflang de
 * `_canonical_route`, un attribut de requête qui n'est en réalité jamais peuplé au runtime
 * (seul `_route`, déjà dépourvu du suffixe .en/.fr, et `_locale` le sont). Le bloc restait donc
 * invisible sur toutes les pages publiques, sans qu'aucun test ne le détecte.
 */
final class CanonicalHreflangTest extends WebTestCase
{
    public function testALocalizedPageExposesCanonicalAndHreflangLinks(): void
    {
        $client = static::createClient();

        $client->request('GET', '/guides/gpx-vs-kml');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<link rel="canonical" href="http://localhost/guides/gpx-vs-kml">', $content);
        self::assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/guides/gpx-vs-kml">', $content);
        self::assertStringContainsString('<link rel="alternate" hreflang="fr" href="http://localhost/fr/guides/gpx-ou-kml">', $content);
        self::assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://localhost/guides/gpx-vs-kml">', $content);
    }

    public function testTheFrenchVariantsCanonicalPointsToTheFrenchUrl(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/guides/gpx-ou-kml');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<link rel="canonical" href="http://localhost/fr/guides/gpx-ou-kml">', $content);
    }

    public function testAnAdminPageHasNoCanonicalOrHreflangLinks(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('rel="canonical"', $content);
        self::assertStringNotContainsString('rel="alternate"', $content);
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User(sprintf('canonical-hreflang-admin-%s@example.com', uniqid()));
        $user->setPassword('irrelevant-hash');
        $entityManager->persist($user);
        $entityManager->flush();

        $container->get(PromoteUserToAdminAction::class)->execute($user);

        return $user;
    }
}
