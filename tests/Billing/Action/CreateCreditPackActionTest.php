<?php

declare(strict_types=1);

namespace App\Tests\Billing\Action;

use App\Billing\Action\CreateCreditPackAction;
use App\Billing\Enum\CreditPackBadge;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Request\CreditPackRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateCreditPackActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CreateCreditPackAction $action;
    private CreditPackRepository $creditPackRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(CreateCreditPackAction::class);
        $this->creditPackRepository = $container->get(CreditPackRepository::class);
    }

    public function testItCreatesAPersistedPack(): void
    {
        $request = new CreditPackRequest();
        $request->credits = 42;
        $request->priceCents = 4242;
        $request->currency = 'usd';
        $request->badge = CreditPackBadge::BEST_VALUE;
        $request->displayOrder = 99;
        $request->active = true;

        $pack = $this->action->execute($request);

        self::assertNotNull($pack->getId());
        $found = $this->creditPackRepository->findOneByPublicId((string) $pack->getPublicId());
        self::assertNotNull($found);
        self::assertSame(42, $found->getCredits());
        self::assertSame(4242, $found->getPriceCents());
        self::assertSame(CreditPackBadge::BEST_VALUE, $found->getBadge());

        $this->entityManager->remove($pack);
        $this->entityManager->flush();
    }
}
