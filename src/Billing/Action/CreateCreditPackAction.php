<?php

declare(strict_types=1);

namespace App\Billing\Action;

use App\Billing\Entity\CreditPack;
use App\Billing\Request\CreditPackRequest;
use Doctrine\ORM\EntityManagerInterface;

final class CreateCreditPackAction
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function execute(CreditPackRequest $request): CreditPack
    {
        $pack = new CreditPack(
            $request->credits,
            $request->priceCents,
            $request->currency,
            $request->badge,
            $request->displayOrder,
            $request->active,
        );

        $this->entityManager->persist($pack);
        $this->entityManager->flush();

        return $pack;
    }
}
