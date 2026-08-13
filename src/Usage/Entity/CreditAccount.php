<?php

declare(strict_types=1);

namespace App\Usage\Entity;

use App\Identity\Entity\User;
use App\Shared\Doctrine\TimestampableTrait;
use App\Usage\Repository\CreditAccountRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * balance = crédits immédiatement dépensables ; reserved = crédits actuellement bloqués par une
 * conversion en cours. Invariant : balance + reserved == SUM(credit_transaction.amount) pour ce
 * compte (voir documentation/decisions/ADR-002-credit-ledger.md).
 */
#[ORM\Entity(repositoryClass: CreditAccountRepository::class)]
#[ORM\Table(name: 'credit_account')]
#[ORM\UniqueConstraint(name: 'uniq_credit_account_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class CreditAccount
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $balance = 0;

    #[ORM\Column]
    private int $reserved = 0;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

    /**
     * Réservé à la création du compte (voir GrantWelcomeCreditAction) : sur une entité pas
     * encore persistée, aucun risque de concurrence. Toute variation de solde sur un compte
     * existant doit obligatoirement passer par CreditAccountRepository (SQL atomique), jamais
     * par cette méthode ni par un flush() de l'entity manager.
     */
    public function initializeBalance(int $amount): void
    {
        $this->balance = $amount;
    }
}
