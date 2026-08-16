<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\CreditPackSlug;
use App\Billing\Enum\CreditPurchaseStatus;
use App\Billing\Repository\CreditPurchaseRepository;
use App\Identity\Entity\User;
use App\Shared\Doctrine\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

/**
 * Suit une session Stripe Checkout de sa création (PENDING) à sa confirmation par webhook
 * (COMPLETED) — clé de l'idempotence face aux livraisons "at-least-once" de Stripe, voir
 * documentation/decisions/ADR-006-billing-provider.md. credits/amountCents/currency sont figés
 * au moment de l'achat (pas relus depuis CreditPack ensuite) : même logique d'enregistrement
 * immuable que CreditTransaction.
 */
#[ORM\Entity(repositoryClass: CreditPurchaseRepository::class)]
#[ORM\Table(name: 'credit_purchase')]
#[ORM\UniqueConstraint(name: 'uniq_credit_purchase_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_credit_purchase_stripe_checkout_session_id', columns: ['stripe_checkout_session_id'])]
#[ORM\Index(name: 'idx_credit_purchase_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class CreditPurchase
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

    #[ORM\ManyToOne(targetEntity: CreditPack::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CreditPack $creditPack;

    #[ORM\Column]
    private int $credits;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 255)]
    private string $stripeCheckoutSessionId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 20, enumType: CreditPurchaseStatus::class)]
    private CreditPurchaseStatus $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * Distinct de completedAt : ne garde pas si l'événement GA4 "purchase" a déjà été envoyé au
     * navigateur, indépendamment du crédit lui-même — voir documentation/technique/
     * google-tag-manager.md. Même idiome que markCompleted() (timestamp nullable posé une fois).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $analyticsTrackedAt = null;

    public function __construct(User $user, CreditPack $creditPack, string $stripeCheckoutSessionId)
    {
        $this->publicId = new UuidV7();
        $this->user = $user;
        $this->creditPack = $creditPack;
        $this->credits = $creditPack->getCredits();
        $this->amountCents = $creditPack->getPriceCents();
        $this->currency = $creditPack->getCurrency();
        $this->stripeCheckoutSessionId = $stripeCheckoutSessionId;
        $this->status = CreditPurchaseStatus::PENDING;
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

    public function getCreditPack(): CreditPack
    {
        return $this->creditPack;
    }

    public function getCredits(): int
    {
        return $this->credits;
    }

    /**
     * Dérivé de credits (figé au moment de l'achat), pas de creditPack.getAnalyticsSlug() : reste
     * correct même si le pack d'origine a depuis été modifié ou désactivé — voir CreditPackSlug.
     */
    public function getAnalyticsSlug(): string
    {
        return CreditPackSlug::forCredits($this->credits);
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStripeCheckoutSessionId(): string
    {
        return $this->stripeCheckoutSessionId;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): void
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
    }

    public function getStatus(): CreditPurchaseStatus
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return CreditPurchaseStatus::COMPLETED === $this->status;
    }

    public function markCompleted(): void
    {
        $this->status = CreditPurchaseStatus::COMPLETED;
        $this->completedAt ??= new \DateTimeImmutable();
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isAnalyticsTracked(): bool
    {
        return null !== $this->analyticsTrackedAt;
    }

    /**
     * @return bool true si cet appel est celui qui vient de marquer l'achat (false s'il l'était déjà)
     */
    public function markAnalyticsTracked(): bool
    {
        if (null !== $this->analyticsTrackedAt) {
            return false;
        }

        $this->analyticsTrackedAt = new \DateTimeImmutable();

        return true;
    }

    public function getAnalyticsTrackedAt(): ?\DateTimeImmutable
    {
        return $this->analyticsTrackedAt;
    }
}
