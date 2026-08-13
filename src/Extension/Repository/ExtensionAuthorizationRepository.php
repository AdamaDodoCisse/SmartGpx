<?php

declare(strict_types=1);

namespace App\Extension\Repository;

use App\Extension\Entity\ExtensionAuthorization;
use App\Identity\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ExtensionAuthorization>
 */
class ExtensionAuthorizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtensionAuthorization::class);
    }

    /**
     * Chemin chaud de l'authentification par jeton — appelé sur chaque requête de l'extension.
     */
    public function findActiveByTokenHash(string $tokenHash): ?ExtensionAuthorization
    {
        return $this->findOneBy(['tokenHash' => $tokenHash, 'revokedAt' => null]);
    }

    /**
     * @return list<ExtensionAuthorization>
     */
    public function findAllForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findOneByPublicIdForUser(User $user, string $publicId): ?ExtensionAuthorization
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->findOneBy(['user' => $user, 'publicId' => $publicId]);
    }

    /**
     * UPDATE brut hors unit of work Doctrine : évite un cycle de flush complet sur chaque appel
     * authentifié de l'extension — même convention que CreditAccountRepository::reserveOne().
     */
    public function touchLastUsedAt(ExtensionAuthorization $authorization): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE extension_authorization SET last_used_at = :now WHERE id = :id',
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'id' => $authorization->getId()],
        );
    }
}
