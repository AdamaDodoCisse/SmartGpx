<?php

declare(strict_types=1);

namespace App\Conversion\Controller;

use App\Conversion\Action\PreviewGoogleMapsRoutesAction;
use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Request\ConvertGoogleMapsUrlRequest;
use App\Conversion\Service\GoogleMapsRouteOptionsMapper;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
use App\Routing\Exception\TooManyWaypointsException;
use App\Routing\Provider\RoutingProviderInterface;
use App\Routing\Result\RouteResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Première étape du flux "choisir son itinéraire" (options avancées : itinéraires alternatifs,
 * route de référence économe en carburant) — ne facture rien, voir PreviewGoogleMapsRoutesAction
 * et documentation/technique/routing-options.md.
 */
final class PreviewGoogleMapsRoutesController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.conversion')]
        private readonly RateLimiterFactory $conversionLimiterFactory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly GoogleMapsRouteOptionsMapper $optionsMapper,
        private readonly RoutingProviderInterface $routingProvider,
    ) {
    }

    #[Route('/api/conversions/google-maps/preview', name: 'app_api_preview_google_maps_routes', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(Request $request, PreviewGoogleMapsRoutesAction $previewGoogleMapsRoutesAction): JsonResponse
    {
        $user = $this->currentUser();

        if (!$this->isCsrfTokenValid('convert_google_maps', $request->headers->get('X-CSRF-Token'))) {
            return $this->errorResponse('conversion.error.invalid_csrf', $user, Response::HTTP_CONFLICT);
        }

        $limiter = $this->conversionLimiterFactory->create($user->getUserIdentifier());

        if (!$limiter->consume(1)->isAccepted()) {
            return $this->errorResponse('conversion.error.too_many_requests', $user, Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->errorResponse('conversion.error.invalid_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dto = ConvertGoogleMapsUrlRequest::fromPayload($payload);

        if (\count($this->validator->validate($dto)) > 0) {
            return $this->errorResponse('conversion.error.invalid_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $travelModeOverride = null !== $dto->travelMode
            ? TravelMode::tryFrom(strtoupper($dto->travelMode))
            : null;

        $mapping = $this->optionsMapper->map($dto, $this->routingProvider->capabilities());

        try {
            $preview = $previewGoogleMapsRoutesAction->execute(
                $user,
                $dto->url,
                $travelModeOverride ?? $mapping->presetSuggestedTravelMode,
                $mapping->options,
                $mapping->waypointTypes,
            );
        } catch (EmailNotVerifiedException) {
            return $this->errorResponse('conversion.error.email_not_verified', $user, Response::HTTP_FORBIDDEN);
        } catch (InvalidGoogleMapsUrlException|UnsupportedGoogleMapsUrlException) {
            return $this->errorResponse('conversion.error.unsupported_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RouteNotFoundException) {
            return $this->errorResponse('conversion.error.route_not_found', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (TooManyWaypointsException) {
            return $this->errorResponse('conversion.error.too_many_waypoints', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RoutingProviderUnavailableException) {
            return $this->errorResponse('conversion.error.provider_unavailable', $user, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'previewId' => $preview->previewId,
            'candidates' => array_map(
                static fn (RouteResult $route, int $index): array => [
                    'index' => $index,
                    'routeLabel' => $route->routeLabel,
                    'distanceMeters' => $route->distanceMeters,
                    'durationSeconds' => $route->durationSeconds,
                    'avoidsHighways' => $mapping->options->modifiers->avoidHighways,
                    'avoidsTolls' => $mapping->options->modifiers->avoidTolls,
                    'tollEstimate' => null !== $route->tollEstimate
                        ? ['currencyCode' => $route->tollEstimate->currencyCode, 'amount' => $route->tollEstimate->amount]
                        : null,
                ],
                $preview->computation->routes,
                array_keys($preview->computation->routes),
            ),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function errorResponse(string $translationKey, User $user, int $statusCode): JsonResponse
    {
        return $this->json(
            ['error' => $this->translator->trans($translationKey, [], null, $user->getLocale())],
            $statusCode,
        );
    }
}
