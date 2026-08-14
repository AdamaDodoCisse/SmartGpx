<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Entity\CreditPack;
use App\Billing\Entity\CreditPurchase;
use App\Billing\Provider\BillingProviderInterface;
use App\Billing\Result\CheckoutSession;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\UuidV7;

/**
 * Le fournisseur est appelé avant la persistance de CreditPurchase : la ligne référence le
 * véritable identifiant de session Stripe (contrainte NOT NULL + unique), qui n'existe qu'une
 * fois la session créée côté Stripe.
 */
final class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BillingProviderInterface $billingProvider,
    ) {
    }

    public function execute(User $user, CreditPack $pack, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $session = $this->billingProvider->createCheckoutSession(
            customerEmail: $user->getEmail(),
            amountCents: $pack->getPriceCents(),
            currency: $pack->getCurrency(),
            productName: \sprintf('%d SmartGPX credits', $pack->getCredits()),
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            metadata: ['creditPackPublicId' => (string) $pack->getPublicId()],
            idempotencyKey: (string) new UuidV7(),
        );

        $this->entityManager->persist(new CreditPurchase($user, $pack, $session->id));
        $this->entityManager->flush();

        return $session;
    }
}
