<?php

declare(strict_types=1);

namespace App\Conversion\Repository;

use App\Conversion\Entity\Conversion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Conversion>
 */
class ConversionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversion::class);
    }

    public function findOneByPublicId(string $publicId): ?Conversion
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->findOneBy(['publicId' => $publicId]);
    }
}
