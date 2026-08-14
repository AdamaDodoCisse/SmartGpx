<?php

declare(strict_types=1);

namespace App\Usage\Repository;

use App\Identity\Entity\User;
use App\Shared\Pagination\PaginatedResult;
use App\Shared\Pagination\Paginator;
use App\Usage\Entity\CreditAccount;
use App\Usage\Entity\CreditTransaction;
use App\Usage\Enum\CreditTransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditTransaction>
 */
class CreditTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditTransaction::class);
    }

    /**
     * Vrai si l'utilisateur a déjà consommé au moins une conversion — permet de distinguer
     * « 1 conversion gratuite disponible » (crédit de bienvenue jamais entamé) de « N crédits
     * restants » dans les interfaces web et extension.
     */
    public function existsConversionForUser(User $user): bool
    {
        $result = $this->createQueryBuilder('t')
            ->select('1')
            ->innerJoin('t.creditAccount', 'a')
            ->where('a.user = :user')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', CreditTransactionType::CONVERSION)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $result;
    }

    /**
     * @return PaginatedResult<CreditTransaction>
     */
    public function findPageForAccountOrderedByCreatedAt(CreditAccount $creditAccount, Paginator $paginator): PaginatedResult
    {
        /** @var PaginatedResult<CreditTransaction> $result */
        $result = $paginator->paginate(
            $this->createQueryBuilder('t')
                ->where('t.creditAccount = :creditAccount')
                ->setParameter('creditAccount', $creditAccount)
                ->orderBy('t.createdAt', 'DESC'),
        );

        return $result;
    }

    /**
     * Somme des montants (signés) pour un ensemble de types de transaction — utilisé par le
     * tableau de bord admin (crédits émis/consommés).
     *
     * @param list<CreditTransactionType> $types
     */
    public function sumAmountByTypes(array $types): int
    {
        $sum = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.type IN (:types)')
            ->setParameter('types', $types)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $sum ? (int) $sum : 0;
    }
}
