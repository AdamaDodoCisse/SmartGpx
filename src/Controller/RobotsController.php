<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur plutôt que fichier statique : /robots.txt est toujours servi via une vraie
 * requête HTTP, où UrlGeneratorInterface résout déjà le bon hôte absolu sans configuration
 * supplémentaire — un fichier statique obligerait à coder en dur un domaine ou à dupliquer
 * DEFAULT_URI comme seconde source de vérité.
 */
final class RobotsController extends AbstractController
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[Route('/robots.txt', name: 'app_robots')]
    public function __invoke(): Response
    {
        $sitemapUrl = $this->urlGenerator->generate('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $body = <<<TXT
            User-agent: *
            Disallow: /admin
            Disallow: /account/
            Disallow: /api/
            Disallow: /billing/
            Disallow: /reset-password
            Disallow: /register
            Disallow: /verify/
            Disallow: /logout

            Sitemap: {$sitemapUrl}

            TXT;

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
