<?php

declare(strict_types=1);

namespace App\Conversion\Controller;

use App\Conversion\Action\ParseGoogleMapsUrlAction;
use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Routing\ValueObject\RouteLocation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Analyse un lien Google Maps sans calculer d'itinéraire ni facturer de crédit — voir
 * ParseGoogleMapsUrlAction. Accessible aux visiteurs anonymes comme le reste du formulaire
 * principal (voir Phase 9 / CLAUDE.md) : le panneau d'options avancées doit pouvoir peupler sa
 * liste d'étapes STOP/VIA avant même que l'utilisateur se connecte.
 */
final class ParseGoogleMapsUrlController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.route_parse')]
        private readonly RateLimiterFactory $routeParseLimiterFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/conversions/google-maps/parse', name: 'app_api_parse_google_maps', methods: ['POST'])]
    public function parse(Request $request, ParseGoogleMapsUrlAction $parseGoogleMapsUrlAction): JsonResponse
    {
        $limiter = $this->routeParseLimiterFactory->create($request->getClientIp());

        if (!$limiter->consume(1)->isAccepted()) {
            return $this->errorResponse('conversion.error.too_many_requests', $request, Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->errorResponse('conversion.error.invalid_url', $request, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $url = $payload['url'] ?? null;

        if (!\is_string($url) || '' === trim($url)) {
            return $this->errorResponse('conversion.error.invalid_url', $request, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $parsed = $parseGoogleMapsUrlAction->execute($url);
        } catch (InvalidGoogleMapsUrlException|UnsupportedGoogleMapsUrlException) {
            return $this->errorResponse('conversion.error.unsupported_url', $request, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'origin' => $parsed->origin->label(),
            'destination' => $parsed->destination->label(),
            'stops' => array_map(
                static fn (RouteLocation $location, int $index): array => ['label' => $location->label(), 'index' => $index],
                $parsed->intermediates,
                array_keys($parsed->intermediates),
            ),
            'travelMode' => $parsed->travelMode->value,
            'travelModeInferred' => $parsed->travelModeInferred,
        ]);
    }

    private function errorResponse(string $translationKey, Request $request, int $statusCode): JsonResponse
    {
        return $this->json(
            ['error' => $this->translator->trans($translationKey, [], null, $request->getLocale())],
            $statusCode,
        );
    }
}
