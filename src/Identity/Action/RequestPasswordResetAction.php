<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Mailer\IdentityMailer;
use App\Identity\Repository\UserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class RequestPasswordResetAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly IdentityMailer $identityMailer,
    ) {
    }

    /**
     * Toujours silencieux côté appelant (aucune exception, aucun indice sur l'existence du compte) :
     * la page affiche le même message de confirmation, que l'e-mail existe ou non, pour éviter
     * l'énumération de comptes.
     */
    public function execute(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (null === $user) {
            // Génère un token factice pour garder un temps de réponse comparable au cas nominal.
            $this->resetPasswordHelper->generateFakeResetToken();

            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            // Ex. throttling déjà actif pour cet utilisateur : on n'envoie pas de second e-mail.
            return;
        }

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password_token',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->identityMailer->sendPasswordResetEmail($user, $resetUrl);
    }
}
