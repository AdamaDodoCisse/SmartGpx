<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Action\GrantPurchasedCreditsAction;
use App\Billing\Enum\WebhookEventType;
use App\Billing\Exception\CreditPurchaseNotFoundException;
use App\Billing\Exception\InvalidWebhookSignatureException;
use App\Billing\Provider\BillingProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Aucun utilisateur à authentifier ici (voir le firewall api_billing_webhook,
 * config/packages/security.yaml) : la seule garantie de confiance est la vérification de
 * signature effectuée par BillingProviderInterface::parseWebhookEvent() sur le corps brut de la
 * requête — voir documentation/decisions/ADR-006-billing-provider.md. Aucun middleware de
 * désérialisation JSON ne doit jamais être attaché à cette route avant que cette vérification
 * n'ait eu lieu.
 */
final class BillingWebhookController extends AbstractController
{
    #[Route('/billing/webhook/stripe', name: 'app_billing_webhook_stripe', methods: ['POST'])]
    public function stripe(
        Request $request,
        BillingProviderInterface $billingProvider,
        GrantPurchasedCreditsAction $grantPurchasedCreditsAction,
        LoggerInterface $logger,
    ): Response {
        try {
            $event = $billingProvider->parseWebhookEvent($request->getContent(), $request->headers->get('Stripe-Signature'));
        } catch (InvalidWebhookSignatureException $exception) {
            $logger->warning('Rejected Stripe webhook: invalid signature.', ['exception' => $exception]);

            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        if (WebhookEventType::CHECKOUT_SESSION_COMPLETED !== $event->type || null === $event->checkoutSessionId) {
            // Reconnu mais hors périmètre de cette phase (ou UNHANDLED) : accusé de réception
            // sans action, voir documentation/technique/stripe.md.
            return new Response(status: Response::HTTP_OK);
        }

        try {
            $grantPurchasedCreditsAction->execute($event->checkoutSessionId);
        } catch (CreditPurchaseNotFoundException $exception) {
            // Anomalie de données : rejouer la livraison n'y changera rien. On acquitte pour
            // arrêter les tentatives de Stripe, mais on log en erreur pour investigation.
            $logger->error('Stripe webhook references an unknown checkout session.', ['exception' => $exception]);

            return new Response(status: Response::HTTP_OK);
        }
        // Toute autre exception remonte telle quelle → 500 → Stripe reprogramme une nouvelle
        // tentative automatiquement : comportement voulu, pas un bug.

        return new Response(status: Response::HTTP_OK);
    }
}
