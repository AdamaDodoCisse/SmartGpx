<?php

declare(strict_types=1);

namespace App\Extension\Controller;

use App\Conversion\Action\ConvertGoogleMapsToGpxAction;
use App\Conversion\Action\LogConversionFailureAction;
use App\Conversion\Enum\ConversionFailureReason;
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
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
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

/**
 * Équivalent de ConvertGoogleMapsController pour l'extension Chrome (authentification par jeton,
 * pas de CSRF — voir App\Extension\Security\ExtensionTokenAuthenticator et
 * documentation/decisions/ADR-005-extension-authentication.md). Réutilise exactement la même
 * ConvertGoogleMapsToGpxAction et le même limiteur de débit partagé (un utilisateur ne peut pas
 * contourner le plafond de 20/heure en alternant web et extension).
 */
final class ExtensionConversionController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.conversion')]
        private readonly RateLimiterFactory $conversionLimiterFactory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly ConversionJsonPresenter $presenter,
    ) {
    }

    #[Route('/api/extension/conversions/google-maps', name: 'app_api_extension_convert_google_maps', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        ConvertGoogleMapsToGpxAction $convertGoogleMapsToGpxAction,
        LogConversionFailureAction $logConversionFailureAction,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $user = $this->currentUser();

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
            $logConversionFailureAction->execute($user, $dto->url, ConversionFailureReason::UNSUPPORTED_URL);

            return $this->errorResponse('conversion.error.unsupported_url', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InsufficientCreditsException) {
            $logConversionFailureAction->execute($user, $dto->url, ConversionFailureReason::INSUFFICIENT_CREDITS);

            return $this->errorResponse('conversion.error.insufficient_credits', $user, Response::HTTP_PAYMENT_REQUIRED);
        } catch (RouteNotFoundException) {
            $logConversionFailureAction->execute($user, $dto->url, ConversionFailureReason::ROUTE_NOT_FOUND);

            return $this->errorResponse('conversion.error.route_not_found', $user, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RoutingProviderUnavailableException) {
            $logConversionFailureAction->execute($user, $dto->url, ConversionFailureReason::PROVIDER_UNAVAILABLE);

            return $this->errorResponse('conversion.error.provider_unavailable', $user, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $downloadUrl = $urlGenerator->generate('app_api_extension_conversion_download', [
            'publicId' => (string) $conversion->getPublicId(),
        ]);

        return $this->json($this->presenter->toArray($conversion, $downloadUrl));
    }

    #[Route('/api/extension/conversions/{publicId}/gpx', name: 'app_api_extension_conversion_download', methods: ['GET'])]
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

    #[Route('/api/extension/account', name: 'app_api_extension_account', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function account(
        CreditAccountRepository $creditAccountRepository,
        CreditTransactionRepository $creditTransactionRepository,
    ): JsonResponse {
        $user = $this->currentUser();
        $account = $creditAccountRepository->findOneByUser($user);

        return $this->json([
            'creditBalance' => $account?->getBalance() ?? 0,
            'hasEverConverted' => $creditTransactionRepository->existsConversionForUser($user),
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
