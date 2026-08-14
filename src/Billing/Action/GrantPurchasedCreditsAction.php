<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Exception\CreditPurchaseNotFoundException;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Déclenchée par le webhook Stripe checkout.session.completed. Idempotente face aux livraisons
 * "at-least-once" de Stripe : le SELECT ... FOR UPDATE sur la ligne CreditPurchase sérialise deux
 * livraisons concurrentes du même événement, et une session déjà COMPLETED est un no-op — voir
 * documentation/decisions/ADR-006-billing-provider.md.
 */
final class GrantPurchasedCreditsAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreditPurchaseRepository $creditPurchaseRepository,
        private readonly CreditAccountRepository $creditAccountRepository,
    ) {
    }

    /**
     * @throws CreditPurchaseNotFoundException
     */
    public function execute(string $stripeCheckoutSessionId): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $purchase = $this->creditPurchaseRepository->findOneByStripeCheckoutSessionIdForUpdate($stripeCheckoutSessionId);

            if (null === $purchase) {
                throw new CreditPurchaseNotFoundException($stripeCheckoutSessionId);
            }

            if ($purchase->isCompleted()) {
                // Livraison webhook dupliquée : rien à refaire.
                $connection->commit();

                return;
            }

            $creditAccount = $this->creditAccountRepository->findOneByUserOrFail($purchase->getUser());
            $balanceAfter = $this->creditAccountRepository->creditBalance($purchase->getUser(), $purchase->getCredits());

            $this->entityManager->persist(new CreditTransaction(
                $creditAccount,
                CreditTransactionType::PURCHASE,
                $purchase->getCredits(),
                $balanceAfter,
                creditPurchaseId: $purchase->getId(),
            ));
            $purchase->markCompleted();
            $this->entityManager->flush();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
