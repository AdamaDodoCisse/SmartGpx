<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Action\RequestPasswordResetAction;
use App\Identity\Form\ForgotPasswordFormType;
use App\Identity\Request\ForgotPasswordRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset_request')]
        private readonly RateLimiterFactory $passwordResetLimiterFactory,
    ) {
    }

    #[Route(['en' => '/forgot-password', 'fr' => '/fr/forgot-password'], name: 'app_forgot_password_request')]
    public function request(Request $request, RequestPasswordResetAction $requestPasswordResetAction): Response
    {
        $dto = new ForgotPasswordRequest();
        $form = $this->createForm(ForgotPasswordFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->passwordResetLimiterFactory->create($request->getClientIp());

            // Si la limite est atteinte, on n'envoie pas de second e-mail mais on affiche
            // le même message générique : ne jamais révéler l'état du compte ou du throttling.
            if ($limiter->consume(1)->isAccepted()) {
                $requestPasswordResetAction->execute($dto->email);
            }

            $this->addFlash('success', 'identity.flash.password_reset_requested');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('identity/forgot_password.html.twig', [
            'requestForm' => $form,
        ]);
    }
}
