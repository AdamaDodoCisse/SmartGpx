<?php

declare(strict_types=1);

namespace App\Contact\Mailer;

use App\Contact\Request\ContactRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'string:MAILER_FROM_ADDRESS')]
        private readonly string $fromAddress,
        #[Autowire(env: 'string:MAILER_FROM_NAME')]
        private readonly string $fromName,
        #[Autowire(env: 'string:CONTACT_RECIPIENT_EMAIL')]
        private readonly string $recipientAddress,
    ) {
    }

    /**
     * replyTo pointe vers l'expéditeur du formulaire : répondre depuis la boîte de réception
     * revient à écrire directement à la personne, sans avoir à recopier son adresse.
     */
    public function sendContactMessage(ContactRequest $request): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($this->recipientAddress)
            ->replyTo(new Address($request->email, $request->name))
            ->htmlTemplate('contact/emails/message.html.twig')
            ->context([
                'name' => $request->name,
                'submitterEmail' => $request->email,
                'contactMessage' => $request->message,
            ])
        ;

        $this->mailer->send($email);
    }
}
