<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Action\ResetPasswordAction;
use App\Identity\Entity\User;
use App\Identity\Form\ChangePasswordFormType;
use App\Identity\Request\NewPasswordRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordController extends AbstractController
{
    private const string SESSION_TOKEN_KEY = 'reset_password_token';

    /**
     * Capture le token présent dans l'URL de l'e-mail, le déplace en session puis redirige
     * vers une URL sans token : évite qu'il ne se retrouve dans l'historique du navigateur
     * ou les logs du referer.
     */
    #[Route(['en' => '/reset-password/{token}', 'fr' => '/fr/reset-password/{token}'], name: 'app_reset_password_token', requirements: ['token' => '.+'])]
    public function captureToken(string $token, Request $request): Response
    {
        $request->getSession()->set(self::SESSION_TOKEN_KEY, $token);

        return $this->redirectToRoute('app_reset_password');
    }

    #[Route(['en' => '/reset-password', 'fr' => '/fr/reset-password'], name: 'app_reset_password')]
    public function reset(
        Request $request,
        ResetPasswordHelperInterface $resetPasswordHelper,
        ResetPasswordAction $resetPasswordAction,
    ): Response {
        $token = $request->getSession()->get(self::SESSION_TOKEN_KEY);

        if (!\is_string($token)) {
            throw $this->createNotFoundException('No reset password token found in the session.');
        }

        try {
            $user = $resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $request->getSession()->remove(self::SESSION_TOKEN_KEY);
            $this->addFlash('error', 'identity.flash.reset_password_invalid');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        \assert($user instanceof User);

        $dto = new NewPasswordRequest();
        $form = $this->createForm(ChangePasswordFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->remove(self::SESSION_TOKEN_KEY);
            $resetPasswordAction->execute($user, $token, $dto->plainPassword);

            $this->addFlash('success', 'identity.flash.reset_password_success');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('identity/reset_password.html.twig', [
            'resetForm' => $form,
        ]);
    }
}
