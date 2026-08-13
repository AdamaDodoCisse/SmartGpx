<?php

declare(strict_types=1);

namespace App\Conversion\Controller;

use App\Conversion\Action\ConvertGoogleMapsToGpxAction;
use App\Conversion\Exception\InvalidGoogleMapsUrlException;
use App\Conversion\Exception\UnsupportedGoogleMapsUrlException;
use App\Conversion\Gpx\GpxGenerator;
use App\Conversion\Http\ConversionJsonPresenter;
use App\Conversion\Repository\ConversionRepository;
use App\Conversion\Request\ConvertGoogleMapsUrlRequest;
use App\Identity\Entity\User;
use App\Routing\Enum\TravelMode;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Exception\RoutingProviderUnavailableException;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConvertGoogleMapsController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.conversion')]
        private readonly RateLimiterFactory $conversionLimiterFactory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly ConversionJsonPresenter $presenter,
    ) {
    }

    #[Route('/api/conversions/google-maps', name: 'app_api_convert_google_maps', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        ConvertGoogleMapsToGpxAction $convertGoogleMapsToGpxAction,
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
            return $this->errorResponse('conversion.error.invalid_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dto = new ConvertGoogleMapsUrlRequest();
        $dto->url = \is_string($payload['url'] ?? null) ? $payload['url'] : '';
        $rawTravelMode = $payload['travelMode'] ?? null;
        $dto->travelMode = \is_string($rawTravelMode) ? $rawTravelMode : null;

        if (\count($this->validator->validate($dto)) > 0) {
            return $this->errorResponse('conversion.error.invalid_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $travelModeOverride = null !== $dto->travelMode
            ? TravelMode::tryFrom(strtoupper($dto->travelMode))
            : null;

        try {
            $conversion = $convertGoogleMapsToGpxAction->execute($user, $dto->url, $travelModeOverride);
        } catch (InvalidGoogleMapsUrlException|UnsupportedGoogleMapsUrlException) {
            return $this->errorResponse('conversion.error.unsupported_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException) {
            return $this->errorResponse('conversion.error.insufficient_credits', $user, Response::HTTP_PAYMENT_REQUIRED);
        } catch (RouteNotFoundException) {
            return $this->errorResponse('conversion.error.route_not_found', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RoutingProviderUnavailableException) {
            return $this->errorResponse('conversion.error.provider_unavailable', $user, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $downloadUrl = $urlGenerator->generate('app_api_conversion_download', [
            'publicId' => (string) $conversion->getPublicId(),
        ]);

        return $this->json($this->presenter->toArray($conversion, $downloadUrl));
    }

    #[Route('/api/conversions/{publicId}/gpx', name: 'app_api_conversion_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function download(
        string $publicId,
        ConversionRepository $conversionRepository,
        GpxGenerator $gpxGenerator,
    ): Response {
        $user = $this->currentUser();
        $conversion = $conversionRepository->findOneByPublicId($publicId);

        if (null === $conversion || $conversion->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return new Response($gpxGenerator->generate($conversion->toGpxRouteData()), Response::HTTP_OK, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => sprintf('attachment; filename="smartgpx-%s.gpx"', $conversion->getPublicId()),
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
