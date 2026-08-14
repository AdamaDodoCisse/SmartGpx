<?php

declare(strict_types=1);

namespace App\Conversion\Entity;

use App\Conversion\Enum\ConversionFailureReason;
use App\Conversion\Repository\ConversionFailureRepository;
use App\Identity\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Enregistrement immuable d'une tentative de conversion Google Maps → GPX échouée — jamais
 * modifié après création (pas de TimestampableTrait, comme CreditTransaction). Entité séparée de
 * Conversion plutôt que colonnes nullables sur celle-ci : le docblock de Conversion garantit que
 * chaque ligne est une conversion réussie, une garantie que des colonnes d'échec nullables
 * casseraient. Pas de publicId : liste admin uniquement, rien ne référence une ligne précise par
 * URL (même raisonnement que CreditTransaction, qui n'en a pas non plus).
 */
#[ORM\Entity(repositoryClass: ConversionFailureRepository::class)]
#[ORM\Table(name: 'conversion_failure')]
#[ORM\Index(name: 'idx_conversion_failure_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_conversion_failure_created_at', columns: ['created_at'])]
class ConversionFailure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $sourceUrl;

    #[ORM\Column(length: 30, enumType: ConversionFailureReason::class)]
    private ConversionFailureReason $reason;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $sourceUrl, ConversionFailureReason $reason)
    {
        $this->user = $user;
        $this->sourceUrl = $sourceUrl;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getReason(): ConversionFailureReason
    {
        return $this->reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
