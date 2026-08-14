<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\CreditPack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<CreditPack>
 */
class CreditPackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditPack::class);
    }

    /**
     * @return list<CreditPack>
     */
    public function findActiveOrderedForDisplay(): array
    {
        return $this->findBy(['active' => true], ['displayOrder' => 'ASC']);
    }

    public function findOneActiveByPublicId(string $publicId): ?CreditPack
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->findOneBy(['publicId' => $publicId, 'active' => true]);
    }
}
