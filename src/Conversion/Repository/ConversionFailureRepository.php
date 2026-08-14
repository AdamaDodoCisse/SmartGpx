<?php

declare(strict_types=1);

namespace App\Conversion\Repository;

use App\Conversion\Entity\ConversionFailure;
use App\Shared\Pagination\PaginatedResult;
use App\Shared\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversionFailure>
 */
class ConversionFailureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversionFailure::class);
    }

    /**
     * @return PaginatedResult<ConversionFailure>
     */
    public function findPageOrderedByCreatedAt(Paginator $paginator): PaginatedResult
    {
        /** @var PaginatedResult<ConversionFailure> $result */
        $result = $paginator->paginate($this->createQueryBuilder('f')->orderBy('f.createdAt', 'DESC'));

        return $result;
    }
}
