<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\CreditPackSlug;
use App\Billing\Enum\CreditPackBadge;
use App\Billing\Repository\CreditPackRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

/**
 * Catalogue des packs de crédits achetables — voir documentation/fonctionnel/pricing.md et
 * documentation/technique/admin.md (CRUD admin, Phase 8).
 */
#[ORM\Entity(repositoryClass: CreditPackRepository::class)]
#[ORM\Table(name: 'credit_pack')]
#[ORM\UniqueConstraint(name: 'uniq_credit_pack_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class CreditPack
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private UuidV7 $publicId;

    #[ORM\Column]
    private int $credits;

    #[ORM\Column]
    private int $priceCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 20, nullable: true, enumType: CreditPackBadge::class)]
    private ?CreditPackBadge $badge;

    #[ORM\Column]
    private int $displayOrder;

    #[ORM\Column]
    private bool $active;

    public function __construct(
        int $credits,
        int $priceCents,
        string $currency,
        ?CreditPackBadge $badge,
        int $displayOrder,
        bool $active = true,
    ) {
        $this->publicId = new UuidV7();
        $this->credits = $credits;
        $this->priceCents = $priceCents;
        $this->currency = $currency;
        $this->badge = $badge;
        $this->displayOrder = $displayOrder;
        $this->active = $active;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): UuidV7
    {
        return $this->publicId;
    }

    public function getCredits(): int
    {
        return $this->credits;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBadge(): ?CreditPackBadge
    {
        return $this->badge;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getAnalyticsSlug(): string
    {
        return CreditPackSlug::forCredits($this->credits);
    }

    public function update(
        int $credits,
        int $priceCents,
        string $currency,
        ?CreditPackBadge $badge,
        int $displayOrder,
        bool $active,
    ): void {
        $this->credits = $credits;
        $this->priceCents = $priceCents;
        $this->currency = $currency;
        $this->badge = $badge;
        $this->displayOrder = $displayOrder;
        $this->active = $active;
    }
}
