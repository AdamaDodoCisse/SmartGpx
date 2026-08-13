<?php

declare(strict_types=1);

namespace App\Extension\Controller;

use App\Extension\Action\GenerateExtensionAuthorizationAction;
use App\Extension\Action\RevokeExtensionAuthorizationAction;
use App\Extension\Exception\ExtensionAuthorizationNotFoundException;
use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de compte listant/gérant les autorisations d'extension (ExtensionAuthorization) —
 * ordinaire firewall "main" (session), pas le firewall "api_extension". Sert aussi de cible à la
 * prise de contact de l'extension : voir templates/extension/connect.html.twig et
 * assets/app/src/entries/extensionConnect.tsx.
 */
final class AccountExtensionController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'string:EXTENSION_CHROME_ID')]
        private readonly string $extensionChromeId,
    ) {
    }

    #[Route(['en' => '/account/extensions', 'fr' => '/fr/compte/extensions'], name: 'app_account_extensions_index')]
    #[IsGranted('ROLE_USER')]
    public function index(ExtensionAuthorizationRepository $repository): Response
    {
        return $this->render('extension/index.html.twig', [
            'authorizations' => $repository->findAllForUser($this->currentUser()),
        ]);
    }

    #[Route(['en' => '/account/extensions/connect', 'fr' => '/fr/compte/extensions/connecter'], name: 'app_account_extensions_connect', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function connect(Request $request, GenerateExtensionAuthorizationAction $generateExtensionAuthorizationAction): Response
    {
        if (!$this->isCsrfTokenValid('extension_connect', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $generated = $generateExtensionAuthorizationAction->execute(
            $this->currentUser(),
            $this->guessLabel($request),
        );

        $response = $this->render('extension/connect.html.twig', [
            'plainToken' => $generated->plainToken,
            'extensionChromeId' => $this->extensionChromeId,
            'apiOrigin' => $request->getSchemeAndHttpHost(),
        ]);

        // La page ne doit jamais être mise en cache ni indexée : elle porte un jeton en clair.
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route(['en' => '/account/extensions/{publicId}/revoke', 'fr' => '/fr/compte/extensions/{publicId}/revoquer'], name: 'app_account_extensions_revoke', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function revoke(string $publicId, Request $request, RevokeExtensionAuthorizationAction $revokeExtensionAuthorizationAction): Response
    {
        if (!$this->isCsrfTokenValid('extension_revoke', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $revokeExtensionAuthorizationAction->execute($this->currentUser(), $publicId);
        } catch (ExtensionAuthorizationNotFoundException) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('success', 'extension.account.flash.revoked');

        return $this->redirectToRoute('app_account_extensions_index');
    }

    private function guessLabel(Request $request): ?string
    {
        $userAgent = $request->headers->get('User-Agent');

        return \is_string($userAgent) && '' !== $userAgent ? mb_substr($userAgent, 0, 255) : null;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
