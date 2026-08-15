<?php

declare(strict_types=1);

namespace App\Conversion\Controller;

use App\Routing\Provider\RoutingProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expose RoutingProviderCapabilities du fournisseur actif — public, statique (n'est pas
 * spécifique à un utilisateur), utilisé par le panneau d'options avancées pour n'afficher que ce
 * que le fournisseur actif supporte réellement. Voir documentation/technique/routing-provider.md.
 */
final class RoutingCapabilitiesController extends AbstractController
{
    #[Route('/api/routing/capabilities', name: 'app_api_routing_capabilities', methods: ['GET'])]
    public function __invoke(RoutingProviderInterface $routingProvider): JsonResponse
    {
        $response = $this->json($routingProvider->capabilities()->toArray());
        $response->setSharedMaxAge(300);
        $response->headers->addCacheControlDirective('public');

        return $response;
    }
}
