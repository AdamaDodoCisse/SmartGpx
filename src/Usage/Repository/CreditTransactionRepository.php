<?php

declare(strict_types=1);

namespace App\Usage\Repository;

use App\Usage\Entity\CreditTransaction;
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
}
