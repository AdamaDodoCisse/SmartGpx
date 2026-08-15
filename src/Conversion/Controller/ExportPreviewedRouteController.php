<?php

declare(strict_types=1);

namespace App\Conversion\Controller;

use App\Conversion\Action\ExportPreviewedRouteAction;
use App\Conversion\Action\LogConversionFailureAction;
use App\Conversion\Enum\ConversionFailureReason;
use App\Conversion\Exception\InvalidRouteSelectionException;
use App\Conversion\Exception\RoutePreviewNotFoundException;
use App\Conversion\Http\ConversionJsonPresenter;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Usage\Exception\InsufficientCreditsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Deuxième étape du flux "choisir son itinéraire" — facture réellement le crédit sur l'itinéraire
 * choisi. Voir ExportPreviewedRouteAction et PreviewGoogleMapsRoutesController.
 */
final class ExportPreviewedRouteController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.conversion')]
        private readonly RateLimiterFactory $conversionLimiterFactory,
        private readonly TranslatorInterface $translator,
        private readonly ConversionJsonPresenter $presenter,
    ) {
    }

    #[Route('/api/conversions/google-maps/export', name: 'app_api_export_previewed_route', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function export(
        Request $request,
        ExportPreviewedRouteAction $exportPreviewedRouteAction,
        LogConversionFailureAction $logConversionFailureAction,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
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
            return $this->errorResponse('conversion.error.invalid_route_selection', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previewId = $payload['previewId'] ?? null;
        $selectedIndex = $payload['selectedIndex'] ?? null;

        if (!\is_string($previewId) || '' === $previewId || !\is_int($selectedIndex)) {
            return $this->errorResponse('conversion.error.invalid_route_selection', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $conversion = $exportPreviewedRouteAction->execute($user, $previewId, $selectedIndex);
        } catch (EmailNotVerifiedException) {
            return $this->errorResponse('conversion.error.email_not_verified', $user, Response::HTTP_FORBIDDEN);
        } catch (RoutePreviewNotFoundException) {
            return $this->errorResponse('conversion.error.preview_not_found', $user, Response::HTTP_GONE);
        } catch (InvalidRouteSelectionException) {
            return $this->errorResponse('conversion.error.invalid_route_selection', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException) {
            $logConversionFailureAction->execute($user, sprintf('preview:%s', $previewId), ConversionFailureReason::INSUFFICIENT_CREDITS);

            return $this->errorResponse('conversion.error.insufficient_credits', $user, Response::HTTP_PAYMENT_REQUIRED);
        }

        $downloadUrl = $urlGenerator->generate('app_api_conversion_download', [
            'publicId' => (string) $conversion->getPublicId(),
        ]);

        return $this->json($this->presenter->toArray($conversion, $downloadUrl));
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
