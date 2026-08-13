<?php

declare(strict_types=1);

namespace App\Extension\Security;

use App\Extension\Repository\ExtensionAuthorizationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Authentification sans état de l'extension Chrome (et de tout futur client non-navigateur) via
 * un jeton opaque révocable — voir documentation/decisions/ADR-005-extension-authentication.md.
 * CSRF ne s'applique pas ici : un en-tête Authorization posé explicitement par le code de
 * l'extension n'est jamais une créance ambiante qu'une page tierce pourrait forger.
 */
final class ExtensionTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ExtensionAuthorizationRepository $repository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), \strlen('Bearer '));

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('extension.error.invalid_token');
        }

        $authorization = $this->repository->findActiveByTokenHash(hash('sha256', $token));

        if (null === $authorization) {
            throw new CustomUserMessageAuthenticationException('extension.error.invalid_token');
        }

        $this->repository->touchLastUsedAt($authorization);

        $user = $authorization->getUser();

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(
            // Aucun User résolu en cas d'échec : traduction dans la locale par défaut.
            ['error' => $this->translator->trans('extension.error.invalid_token')],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
