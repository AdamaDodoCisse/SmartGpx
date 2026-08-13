<?php

declare(strict_types=1);

namespace App\Identity\Mailer;

use App\Identity\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class IdentityMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'string:MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        #[Autowire(env: 'string:MAILER_FROM_NAME')]
        private readonly string $fromName,
    ) {
    }

    public function sendVerificationEmail(User $user, string $signedVerificationUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($user->getEmail())
            ->htmlTemplate('identity/emails/confirm_email.html.twig')
            ->context([
                'signedVerificationUrl' => $signedVerificationUrl,
            ])
        ;

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user, string $resetUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($user->getEmail())
            ->htmlTemplate('identity/emails/reset_password.html.twig')
            ->context([
                'resetUrl' => $resetUrl,
            ])
        ;

        $this->mailer->send($email);
    }
}
