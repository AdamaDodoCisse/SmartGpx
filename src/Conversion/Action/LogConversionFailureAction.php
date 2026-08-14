<?php

declare(strict_types=1);

namespace App\Conversion\Action;

use App\Conversion\Entity\ConversionFailure;
use App\Conversion\Enum\ConversionFailureReason;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hors de toute transaction explicite : aucun invariant financier n'est en jeu ici, la
 * réservation de crédit a déjà été relâchée par ConvertGoogleMapsToGpxAction avant que
 * l'exception n'atteigne le contrôleur appelant.
 */
final class LogConversionFailureAction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function execute(User $user, string $sourceUrl, ConversionFailureReason $reason): void
    {
        $this->entityManager->persist(new ConversionFailure($user, $sourceUrl, $reason));
        $this->entityManager->flush();
    }
}
