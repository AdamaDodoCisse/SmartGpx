<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\User;
use App\Shared\Pagination\PaginatedResult;
use App\Shared\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByGoogleId(string $googleId): ?User
    {
        return $this->findOneBy(['googleId' => $googleId]);
    }

    public function findOneByPublicId(string $publicId): ?User
    {
        if (!Uuid::isValid($publicId)) {
            return null;
        }

        return $this->findOneBy(['publicId' => $publicId]);
    }

    /**
     * @return PaginatedResult<User>
     */
    public function findPageOrderedByCreatedAt(Paginator $paginator): PaginatedResult
    {
        /** @var PaginatedResult<User> $result */
        $result = $paginator->paginate($this->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC'));

        return $result;
    }

    /**
     * Ré-encode automatiquement le mot de passe si l'algorithme de hachage a évolué entre deux connexions.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
