<?php

declare(strict_types=1);

namespace App\Usage\Repository;

use App\Identity\Entity\User;
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
}
