<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\CreditPurchase;
use App\Billing\Enum\CreditPurchaseStatus;
use App\Shared\Pagination\PaginatedResult;
use App\Shared\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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

    public function findOneByPublicId(string $publicId): ?CreditPurchase
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->findOneBy(['publicId' => $publicId]);
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

    /**
     * Verrouille la ligne (SELECT ... FOR UPDATE) pour sérialiser deux appels concurrents de
     * confirmation analytics (deux onglets sur la même page de succès) — voir
     * ConfirmAnalyticsTrackingAction et documentation/technique/google-tag-manager.md. Doit être
     * appelée à l'intérieur d'une transaction déjà ouverte, même principe que
     * findOneByStripeCheckoutSessionIdForUpdate.
     */
    public function findOneByPublicIdForUpdate(string $publicId): ?CreditPurchase
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->where('p.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'uuid')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * @return PaginatedResult<CreditPurchase>
     */
    public function findPageOrderedByCreatedAt(Paginator $paginator): PaginatedResult
    {
        /** @var PaginatedResult<CreditPurchase> $result */
        $result = $paginator->paginate($this->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC'));

        return $result;
    }

    public function countCompleted(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', CreditPurchaseStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumAmountCentsCompleted(): int
    {
        $sum = $this->createQueryBuilder('p')
            ->select('SUM(p.amountCents)')
            ->where('p.status = :status')
            ->setParameter('status', CreditPurchaseStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $sum ? (int) $sum : 0;
    }
}
