<?php

declare(strict_types=1);

namespace App\Usage\Entity;

use App\Usage\Enum\CreditTransactionType;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de ledger immuable — jamais modifiée après création (pas de TimestampableTrait :
 * updatedAt n'aurait aucun sens sur une ligne qui n'est jamais mise à jour).
 *
 * conversionId référence App\Conversion\Entity\Conversion et creditPurchaseId référence
 * App\Billing\Entity\CreditPurchase, tous deux sans relation Doctrine ni contrainte FK : Usage
 * reste indépendant des domaines Conversion et Billing (même logique que Identity qui ignore
 * l'existence de Usage, voir UserRegisteredEvent) — un simple entier suffit pour la traçabilité,
 * une jointure applicative reste possible via leurs repositories respectifs si nécessaire.
 */
#[ORM\Entity(repositoryClass: CreditTransactionRepository::class)]
#[ORM\Table(name: 'credit_transaction')]
#[ORM\Index(name: 'idx_credit_transaction_account', columns: ['credit_account_id'])]
class CreditTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CreditAccount::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CreditAccount $creditAccount;

    #[ORM\Column(length: 20, enumType: CreditTransactionType::class)]
    private CreditTransactionType $type;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column]
    private int $balanceAfter;

    #[ORM\Column(nullable: true)]
    private ?int $conversionId;

    #[ORM\Column(nullable: true)]
    private ?int $creditPurchaseId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        CreditAccount $creditAccount,
        CreditTransactionType $type,
        int $amount,
        int $balanceAfter,
        ?int $conversionId = null,
        ?int $creditPurchaseId = null,
    ) {
        $this->creditAccount = $creditAccount;
        $this->type = $type;
        $this->amount = $amount;
        $this->balanceAfter = $balanceAfter;
        $this->conversionId = $conversionId;
        $this->creditPurchaseId = $creditPurchaseId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreditAccount(): CreditAccount
    {
        return $this->creditAccount;
    }

    public function getType(): CreditTransactionType
    {
        return $this->type;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function getConversionId(): ?int
    {
        return $this->conversionId;
    }

    public function getCreditPurchaseId(): ?int
    {
        return $this->creditPurchaseId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
