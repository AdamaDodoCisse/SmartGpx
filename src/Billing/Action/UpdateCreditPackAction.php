<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Entity\CreditPack;
use App\Billing\Request\CreditPackRequest;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateCreditPackAction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function execute(CreditPack $pack, CreditPackRequest $request): void
    {
        $pack->update(
            $request->credits,
            $request->priceCents,
            $request->currency,
            $request->badge,
            $request->displayOrder,
            $request->active,
        );

        $this->entityManager->flush();
    }
}
