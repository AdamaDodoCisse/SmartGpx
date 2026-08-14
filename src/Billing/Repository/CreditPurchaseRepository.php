<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\CreditPurchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditPurchase>
 */
class CreditPurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditPurchase::class);
    }

    public function findOneByStripeCheckoutSessionId(string $stripeCheckoutSessionId): ?CreditPurchase
    {
        return $this->findOneBy(['stripeCheckoutSessionId' => $stripeCheckoutSessionId]);
    }

    /**
     * Verrouille la ligne (SELECT ... FOR UPDATE) pour sérialiser deux livraisons concurrentes du
     * même événement webhook Stripe. Doit être appelée à l'intérieur d'une transaction déjà
     * ouverte — voir GrantPurchasedCreditsAction et
     * documentation/decisions/ADR-006-billing-provider.md.
     */
    public function findOneByStripeCheckoutSessionIdForUpdate(string $stripeCheckoutSessionId): ?CreditPurchase
    {
        return $this->createQueryBuilder('p')
            ->where('p.stripeCheckoutSessionId = :sessionId')
            ->setParameter('sessionId', $stripeCheckoutSessionId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
