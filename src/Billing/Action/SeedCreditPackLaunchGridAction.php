<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Entity\CreditPack;
use App\Billing\Enum\CreditPackBadge;
use App\Billing\Repository\CreditPackRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Peuple la grille de lancement (voir documentation/fonctionnel/pricing.md) — remplace
 * l'ancienne migration de seed, devenue inutile depuis que le schéma est géré via
 * `doctrine:schema:update --force` plutôt que des migrations. Idempotent : n'insère rien si des
 * packs existent déjà (fraîche installation uniquement, jamais un correctif sur un catalogue
 * déjà modifié depuis l'admin).
 */
final class SeedCreditPackLaunchGridAction
{
    private const array LAUNCH_GRID = [
        ['credits' => 10, 'priceCents' => 499, 'badge' => null, 'displayOrder' => 1],
        ['credits' => 100, 'priceCents' => 999, 'badge' => CreditPackBadge::MOST_POPULAR, 'displayOrder' => 2],
        ['credits' => 500, 'priceCents' => 2999, 'badge' => null, 'displayOrder' => 3],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreditPackRepository $creditPackRepository,
    ) {
    }

    public function execute(): void
    {
        if ($this->creditPackRepository->count([]) > 0) {
            return;
        }

        foreach (self::LAUNCH_GRID as $pack) {
            $this->entityManager->persist(new CreditPack(
                $pack['credits'],
                $pack['priceCents'],
                'usd',
                $pack['badge'],
                $pack['displayOrder'],
            ));
        }

        $this->entityManager->flush();
    }
}
