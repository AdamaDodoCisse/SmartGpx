<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Action\AuthenticateWithGoogleAction;
use App\Identity\Exception\GoogleEmailNotVerifiedException;
use App\Identity\ValueObject\GoogleIdentity;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Un seul Authenticator additionnel enregistré sur le firewall "main" existant — pas de nouvelle
 * interface de domaine (voir architecture.md : Google Sign-In n'a qu'un seul fournisseur réel,
 * contrairement à Routing/Billing). Toute la logique métier (créer/lier/retrouver le User) vit
 * dans AuthenticateWithGoogleAction, seule partie réellement testable sans appel réseau.
 */
final class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly AuthenticateWithGoogleAction $authenticateWithGoogleAction,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $identity = new GoogleIdentity(
            googleId: (string) $googleUser->getId(),
            email: $googleUser->getEmail() ?? '',
            emailVerified: $googleUser->getEmailVerified() ?? false,
        );

        try {
            $user = $this->authenticateWithGoogleAction->execute($identity);
        } catch (GoogleEmailNotVerifiedException) {
            // Le messageKey affiché par login.html.twig est traduit ici (domaine "messages", où
            // vit la clé identity.flash.*) plutôt que dans le domaine "security" que le template
            // interroge : Symfony affiche tel quel un messageKey introuvable dans ce domaine,
            // donc lui passer directement le texte déjà traduit produit le bon résultat.
            throw new CustomUserMessageAuthenticationException($this->translator->trans('identity.flash.google_email_not_verified', locale: $request->getLocale()));
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
