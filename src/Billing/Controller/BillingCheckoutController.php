<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Action\CreateCheckoutSessionAction;
use App\Billing\Exception\BillingProviderUnavailableException;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Session de paiement Stripe Checkout hébergé — voir
 * documentation/decisions/ADR-006-billing-provider.md. Ne crédite jamais de compte directement :
 * seul le webhook signé (BillingWebhookController) le fait, cette page de succès n'étant pas un
 * signal de confiance (session_id devinable/rejouable dans l'URL).
 */
final class BillingCheckoutController extends AbstractController
{
    #[Route(['en' => '/billing/checkout/{publicId}', 'fr' => '/fr/paiement/{publicId}'], name: 'app_billing_checkout_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        string $publicId,
        Request $request,
        CreditPackRepository $creditPackRepository,
        CreateCheckoutSessionAction $createCheckoutSessionAction,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        if (!$this->isCsrfTokenValid('billing_checkout', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $pack = $creditPackRepository->findOneActiveByPublicId($publicId);

        if (null === $pack) {
            throw $this->createNotFoundException();
        }

        // Concaténation volontaire plutôt qu'un paramètre de route généré par Symfony : Stripe
        // remplace lui-même le littéral {CHECKOUT_SESSION_ID}, qui serait autrement encodé en URL.
        $successUrl = $urlGenerator->generate('app_billing_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
            .'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $urlGenerator->generate('app_pricing', ['checkout' => 'cancelled'], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $session = $createCheckoutSessionAction->execute($this->currentUser(), $pack, $successUrl, $cancelUrl);
        } catch (BillingProviderUnavailableException) {
            $this->addFlash('error', 'billing.checkout.error');

            return $this->redirectToRoute('app_pricing');
        }

        return $this->redirect($session->redirectUrl);
    }

    #[Route(['en' => '/billing/checkout/success', 'fr' => '/fr/paiement/succes'], name: 'app_billing_checkout_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Request $request, CreditPurchaseRepository $creditPurchaseRepository): Response
    {
        $sessionId = $request->query->getString('session_id');
        $purchase = '' !== $sessionId ? $creditPurchaseRepository->findOneByStripeCheckoutSessionId($sessionId) : null;

        if (null === $purchase || $purchase->getUser()->getId() !== $this->currentUser()->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('billing/success.html.twig', [
            'purchase' => $purchase,
        ]);
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
