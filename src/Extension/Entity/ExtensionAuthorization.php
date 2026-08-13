<?php

declare(strict_types=1);

namespace App\Extension\Entity;

use App\Extension\Repository\ExtensionAuthorizationRepository;
use App\Identity\Entity\User;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

/**
 * Jeton d'autorisation révocable pour l'extension Chrome (ou tout futur client non-navigateur).
 * Contrairement à CreditTransaction, cette ligne est mutée (lastUsedAt, revokedAt) et n'est
 * jamais supprimée : une autorisation révoquée reste visible sur /account/extensions avec un
 * badge « révoquée le … », même logique de traçabilité que le ledger de crédits appliquée à la
 * révocation plutôt qu'à l'argent.
 */
#[ORM\Entity(repositoryClass: ExtensionAuthorizationRepository::class)]
#[ORM\Table(name: 'extension_authorization')]
#[ORM\UniqueConstraint(name: 'uniq_extension_authorization_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_extension_authorization_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_extension_authorization_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class ExtensionAuthorization
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private UuidV7 $publicId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(User $user, string $tokenHash, ?string $label = null)
    {
        $this->publicId = new UuidV7();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): UuidV7
    {
        return $this->publicId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touchLastUsedAt(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt ??= new \DateTimeImmutable();
    }
}
