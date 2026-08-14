<?php

declare(strict_types=1);

namespace App\Usage\Repository;

use App\Identity\Entity\User;
use App\Usage\Entity\CreditAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Point d'entrée unique des mutations de solde. Toute variation de balance/reserved sur un
 * compte existant passe par une instruction SQL conditionnelle atomique (bypass de l'unit of
 * work Doctrine, qui n'est pas naturellement conditionnel) — voir
 * documentation/decisions/ADR-002-credit-ledger.md pour le raisonnement de concurrence.
 *
 * @extends ServiceEntityRepository<CreditAccount>
 */
class CreditAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditAccount::class);
    }

    public function findOneByUser(User $user): ?CreditAccount
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findOneByUserOrFail(User $user): CreditAccount
    {
        $account = $this->findOneByUser($user);

        if (null === $account) {
            throw new \LogicException(sprintf('No credit account exists for user "%s".', $user->getPublicId()));
        }

        return $account;
    }

    /**
     * Réserve 1 crédit : décrémente balance et incrémente reserved en une seule instruction,
     * conditionnée par balance >= 1. Une UPDATE ... WHERE effectue une lecture "current read"
     * sous InnoDB (verrou de ligne + réévaluation contre la dernière valeur committée) : deux
     * requêtes concurrentes sur la même ligne se sérialisent automatiquement.
     *
     * Volontairement hors de toute transaction explicite : le verrou de ligne n'est tenu que le
     * temps de cette unique instruction, pas pendant tout l'appel externe (lent) qui suit.
     */
    public function reserveOne(User $user): bool
    {
        $affected = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE credit_account SET balance = balance - 1, reserved = reserved + 1 WHERE user_id = :userId AND balance >= 1',
            ['userId' => $user->getId()],
        );

        return $affected > 0;
    }

    /**
     * Décrémente reserved (la réservation est honorée) et renvoie le solde résultant, à utiliser
     * pour renseigner CreditTransaction::balanceAfter. Doit être appelée à l'intérieur de la même
     * transaction/connexion que l'insertion de la ligne de ledger (lecture de sa propre écriture).
     */
    public function decrementReservedAndGetBalance(User $user): int
    {
        $connection = $this->getEntityManager()->getConnection();

        $affected = $connection->executeStatement(
            'UPDATE credit_account SET reserved = reserved - 1 WHERE user_id = :userId AND reserved >= 1',
            ['userId' => $user->getId()],
        );

        if ($affected < 1) {
            throw new \LogicException(sprintf('No active reservation to consume for user "%s".', $user->getPublicId()));
        }

        $balance = $connection->fetchOne(
            'SELECT balance FROM credit_account WHERE user_id = :userId',
            ['userId' => $user->getId()],
        );

        return (int) $balance;
    }

    /**
     * Crédite le compte (achat confirmé) — additif, sans garde conditionnelle contrairement à
     * reserveOne : il n'existe aucun scénario de concurrence où créditer un compte peut
     * légitimement échouer. Renvoie le solde résultant, à utiliser pour
     * CreditTransaction::balanceAfter.
     */
    public function creditBalance(User $user, int $amount): int
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            'UPDATE credit_account SET balance = balance + :amount WHERE user_id = :userId',
            ['amount' => $amount, 'userId' => $user->getId()],
        );

        return (int) $connection->fetchOne(
            'SELECT balance FROM credit_account WHERE user_id = :userId',
            ['userId' => $user->getId()],
        );
    }

    /**
     * Restaure balance et décrémente reserved — aucune ligne de ledger n'est écrite pour un
     * relâchement : rien n'a jamais été définitivement consommé, une conversion échouée coûte 0.
     */
    public function releaseOneReservation(User $user): void
    {
        $affected = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE credit_account SET balance = balance + 1, reserved = reserved - 1 WHERE user_id = :userId AND reserved >= 1',
            ['userId' => $user->getId()],
        );

        if ($affected < 1) {
            throw new \LogicException(sprintf('No active reservation to release for user "%s".', $user->getPublicId()));
        }
    }
}
