<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie uniquement la forme de la redirection vers Google — compléter un vrai login Google en
 * automatisé n'est pas raisonnable ici (voir documentation/technique/google-sign-in.md), même
 * scoping honnête que pour Stripe/Google Routes : vérification manuelle réelle en complément.
 */
final class GoogleConnectControllerTest extends WebTestCase
{
    public function testItRedirectsToGoogleWithTheExpectedScopes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');

        self::assertNotNull($location);
        self::assertStringStartsWith('https://accounts.google.com/', $location);
        // use_oidc_mode ajoute "openid" devant les scopes demandés — voir knpu_oauth2_client.yaml.
        self::assertStringContainsString('scope=openid%20email%20profile', $location);
        self::assertStringContainsString('client_id=changeme', $location);
    }
}
