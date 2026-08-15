<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Action\ConfirmAnalyticsTrackingAction;
use App\Billing\Enum\CreditPurchaseStatus;
use App\Billing\Exception\CreditPurchaseNotFoundException;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Billing\Result\AnalyticsConfirmationResult;
use App\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API JSON consommée par le script de la page de succès Stripe (voir
 * assets/app/src/entries/billingCheckoutSuccess.ts) — jamais un signal de confiance à elle
 * seule, voir BillingCheckoutController. Un seul endpoint, appelé en boucle par le frontend tant
 * que le paiement n'est pas confirmé : voir ConfirmAnalyticsTrackingAction pour pourquoi
 * "vérifier" et "revendiquer" doivent être une seule opération atomique, pas deux appels
 * séparés — documentation/technique/google-tag-manager.md.
 */
final class BillingCheckoutStatusController extends AbstractController
{
    #[Route('/api/billing/checkout/{publicId}/confirm-analytics', name: 'app_api_billing_confirm_analytics', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function confirmAnalytics(
        string $publicId,
        Request $request,
        CreditPurchaseRepository $creditPurchaseRepository,
        ConfirmAnalyticsTrackingAction $confirmAnalyticsTrackingAction,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('billing_confirm_analytics', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'invalid_csrf'], Response::HTTP_CONFLICT);
        }

        $purchase = $creditPurchaseRepository->findOneByPublicId($publicId);

        if (null === $purchase || $purchase->getUser()->getId() !== $this->currentUser()->getId()) {
            throw $this->createNotFoundException();
        }

        try {
            $result = $confirmAnalyticsTrackingAction->execute($publicId);
        } catch (CreditPurchaseNotFoundException) {
            throw $this->createNotFoundException();
        }

        return $this->json($this->present($result));
    }

    /**
     * @return array{status: string, claimed: bool, analytics: array{transactionId: ?string, value: ?float, currency: ?string, credits: ?int, itemId: ?string, itemName: ?string}|null}
     */
    private function present(AnalyticsConfirmationResult $result): array
    {
        return [
            'status' => $result->status->value,
            'claimed' => $result->claimed,
            'analytics' => CreditPurchaseStatus::COMPLETED === $result->status ? [
                'transactionId' => $result->transactionId,
                'value' => $result->value,
                'currency' => $result->currency,
                'credits' => $result->credits,
                'itemId' => $result->itemId,
                'itemName' => $result->itemName,
            ] : null,
        ];
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
