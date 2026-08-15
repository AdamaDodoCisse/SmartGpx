<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Routes volontairement non préfixées par locale (fonctionnelles, pas des pages de contenu —
 * même précédent que les routes d'extension/webhook).
 */
final class GoogleConnectController extends AbstractController
{
    #[Route('/connect/google', name: 'app_connect_google_start')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }

    /**
     * Intentionnellement vide : GoogleAuthenticator intercepte cette route avant que le
     * contrôleur ne soit atteint (même principe que SecurityController::logout()).
     */
    #[Route('/connect/google/check', name: 'app_connect_google_check')]
    public function connectCheck(): never
    {
        throw new \LogicException('This method can be blank — it will be intercepted by GoogleAuthenticator.');
    }
}
