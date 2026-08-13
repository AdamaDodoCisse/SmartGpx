<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Action\RegisterUserAction;
use App\Identity\Exception\EmailAlreadyUsedException;
use App\Identity\Form\RegistrationFormType;
use App\Identity\Mailer\IdentityMailer;
use App\Identity\Request\RegisterUserRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.registration')]
        private readonly RateLimiterFactory $registrationLimiterFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(['en' => '/register', 'fr' => '/fr/register'], name: 'app_register')]
    public function register(
        Request $request,
        RegisterUserAction $registerUserAction,
        VerifyEmailHelperInterface $verifyEmailHelper,
        IdentityMailer $identityMailer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $dto = new RegisterUserRequest();
        $form = $this->createForm(RegistrationFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->registrationLimiterFactory->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'identity.flash.too_many_attempts');

                return $this->redirectToRoute('app_register');
            }

            try {
                $user = $registerUserAction->execute($dto);
            } catch (EmailAlreadyUsedException) {
                $form->get('email')->addError(new FormError(
                    $this->translator->trans('identity.form.email_already_used')
                ));

                return $this->render('identity/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $signature = $verifyEmailHelper->generateSignature(
                'app_verify_email',
                (string) $user->getPublicId(),
                $user->getEmail(),
                ['id' => (string) $user->getPublicId()],
            );

            $identityMailer->sendVerificationEmail($user, $signature->getSignedUrl());

            $this->addFlash('success', 'identity.flash.registration_success');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('identity/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
