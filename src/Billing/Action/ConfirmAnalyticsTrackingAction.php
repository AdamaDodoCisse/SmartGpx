<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Enum\CreditPurchaseStatus;
use App\Billing\Exception\CreditPurchaseNotFoundException;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Billing\Result\AnalyticsConfirmationResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Appelée par le frontend de la page de succès (en boucle tant que le paiement n'est pas encore
 * confirmé, une seule fois utilement ensuite) — jamais l'inverse (jamais "pousser d'abord, puis
 * confirmer"). Le SELECT ... FOR UPDATE sur la ligne CreditPurchase rend l'opération atomique :
 * décider ET marquer la revendication ("claimed") se fait dans la même section critique, donc
 * deux appels concurrents (deux onglets) ne peuvent jamais tous les deux recevoir claimed=true —
 * voir documentation/technique/google-tag-manager.md. Le frontend ne pousse l'événement GA4
 * "purchase" que lorsque claimed=true dans la réponse.
 */
final class ConfirmAnalyticsTrackingAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreditPurchaseRepository $creditPurchaseRepository,
    ) {
    }

    /**
     * @throws CreditPurchaseNotFoundException
     */
    public function execute(string $publicId): AnalyticsConfirmationResult
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $purchase = $this->creditPurchaseRepository->findOneByPublicIdForUpdate($publicId);

            if (null === $purchase) {
                throw new CreditPurchaseNotFoundException($publicId);
            }

            if (!$purchase->isCompleted()) {
                $connection->commit();

                return new AnalyticsConfirmationResult($purchase->getStatus(), claimed: false);
            }

            $claimed = $purchase->markAnalyticsTracked();

            if ($claimed) {
                $this->entityManager->flush();
            }

            $connection->commit();

            return new AnalyticsConfirmationResult(
                CreditPurchaseStatus::COMPLETED,
                $claimed,
                transactionId: 'smartgpx_'.$purchase->getPublicId(),
                value: $purchase->getAmountCents() / 100,
                currency: $purchase->getCurrency(),
                credits: $purchase->getCredits(),
                itemId: (string) $purchase->getCreditPack()->getPublicId(),
                itemName: $purchase->getCredits().' SmartGPX Credits',
            );
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
