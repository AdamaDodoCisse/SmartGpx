<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Action\VerifyUserEmailAction;
use App\Identity\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class VerifyEmailController extends AbstractController
{
    #[Route(['en' => '/verify/email', 'fr' => '/fr/verify/email'], name: 'app_verify_email')]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        VerifyEmailHelperInterface $verifyEmailHelper,
        VerifyUserEmailAction $verifyUserEmailAction,
    ): Response {
        $publicId = $request->query->get('id');

        if (null === $publicId) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->findOneByPublicId($publicId);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getPublicId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'identity.flash.verify_email_error');

            return $this->redirectToRoute('app_register');
        }

        $verifyUserEmailAction->execute($user);

        $this->addFlash('success', 'identity.flash.verify_email_success');

        return $this->redirectToRoute('app_login');
    }
}
