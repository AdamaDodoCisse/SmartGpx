<?php

declare(strict_types=1);

namespace App\Shared\ConvertHero;

use App\Identity\Entity\User;
use App\Routing\Provider\RoutingProviderInterface;
use App\Usage\Repository\CreditAccountRepository;

/**
 * Calcule les deux props dynamiques que tout montage de l'îlot ConvertHero a besoin de recevoir
 * (solde de crédits, capabilities du fournisseur de routing actif) — extrait de HomeController
 * (seul point de montage jusqu'ici) pour être réutilisé tel quel par GuidesController plutôt que
 * dupliqué à chaque nouvel emplacement du convertisseur (cluster Garmin/Wahoo/OsmAnd).
 */
final readonly class ConvertHeroPropsProvider
{
    public function __construct(
        private CreditAccountRepository $creditAccountRepository,
        private RoutingProviderInterface $routingProvider,
    ) {
    }

    /**
     * @return array{creditBalance: int, capabilities: array<string, mixed>}
     */
    public function forUser(?User $user): array
    {
        $creditBalance = 0;

        if ($user instanceof User) {
            $account = $this->creditAccountRepository->findOneByUser($user);
            $creditBalance = $account?->getBalance() ?? 0;
        }

        return [
            'creditBalance' => $creditBalance,
            // Statique (pas propre à l'utilisateur) — évite un aller-retour réseau
            // supplémentaire au montage de l'îlot pour connaître les capabilities du
            // fournisseur actif (voir RoutingCapabilitiesController pour l'équivalent API).
            'capabilities' => $this->routingProvider->capabilities()->toArray(),
        ];
    }
}
