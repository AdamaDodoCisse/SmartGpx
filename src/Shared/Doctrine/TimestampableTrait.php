<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Fournit les colonnes createdAt/updatedAt à toute entité du domaine.
 * La classe qui utilise ce trait doit porter l'attribut #[ORM\HasLifecycleCallbacks].
 */
trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersistTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
