<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\AuthProvider;
use App\Identity\Repository\UserRepository;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_google_id', columns: ['google_id'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private UuidV7 $publicId;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 180)]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    /**
     * Nullable pour permettre, plus tard, des comptes créés uniquement via Google Sign-In.
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 20, enumType: AuthProvider::class)]
    private AuthProvider $authProvider = AuthProvider::LOCAL;

    /**
     * Colonne prête pour le Google Sign-In (Phase 2/4) — non utilisée en Phase 1.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(length: 5)]
    private string $locale = 'en';

    public function __construct(string $email)
    {
        $this->publicId = new UuidV7();
        $this->email = self::requireNonEmptyEmail($email);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): UuidV7
    {
        return $this->publicId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = self::requireNonEmptyEmail($email);

        return $this;
    }

    /**
     * Identifiant visible par le composant Security (ex. utilisé dans la session).
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return non-empty-string
     */
    private static function requireNonEmptyEmail(string $email): string
    {
        if ('' === $email) {
            throw new \InvalidArgumentException('User email cannot be empty.');
        }

        return $email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getAuthProvider(): AuthProvider
    {
        return $this->authProvider;
    }

    public function setAuthProvider(AuthProvider $authProvider): static
    {
        $this->authProvider = $authProvider;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        $this->verifiedAt = $isVerified ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Les données sensibles temporaires (ex. mot de passe en clair) ne sont jamais stockées sur l'entité,
     * donc rien à effacer ici — méthode requise par l'interface.
     */
    public function eraseCredentials(): void
    {
    }
}
