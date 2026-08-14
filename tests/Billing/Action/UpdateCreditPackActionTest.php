<?php

declare(strict_types=1);

namespace App\Tests\Billing\Action;

use App\Billing\Action\UpdateCreditPackAction;
use App\Billing\Entity\CreditPack;
use App\Billing\Repository\CreditPackRepository;
use App\Billing\Request\CreditPackRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UpdateCreditPackActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UpdateCreditPackAction $action;
    private CreditPackRepository $creditPackRepository;
    private CreditPack $pack;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(UpdateCreditPackAction::class);
        $this->creditPackRepository = $container->get(CreditPackRepository::class);

        $this->pack = new CreditPack(10, 999, 'usd', null, 999, true);
        $this->entityManager->persist($this->pack);
        $this->entityManager->flush();
    }

    public function testItUpdatesTheExistingPackInPlace(): void
    {
        $request = new CreditPackRequest();
        $request->credits = 20;
        $request->priceCents = 1999;
        $request->currency = 'eur';
        $request->badge = null;
        $request->displayOrder = 1;
        $request->active = false;

        $this->action->execute($this->pack, $request);

        $this->entityManager->refresh($this->pack);
        self::assertSame(20, $this->pack->getCredits());
        self::assertSame(1999, $this->pack->getPriceCents());
        self::assertSame('eur', $this->pack->getCurrency());
        self::assertSame(1, $this->pack->getDisplayOrder());
        self::assertFalse($this->pack->isActive());
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($this->creditPackRepository->findBy(['credits' => 20]) as $pack) {
            $entityManager->remove($pack);
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
