<?php

declare(strict_types=1);

namespace App\Tests\Billing\Repository;

use App\Billing\Entity\CreditPack;
use App\Billing\Enum\CreditPackBadge;
use App\Billing\Repository\CreditPackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditPackRepositoryTest extends KernelTestCase
{
    public function testFindActiveOrderedForDisplayReturnsTheLaunchGridInOrder(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(CreditPackRepository::class);

        $packs = $repository->findActiveOrderedForDisplay();

        self::assertCount(3, $packs);
        self::assertSame([10, 100, 500], array_map(static fn (CreditPack $pack) => $pack->getCredits(), $packs));
        self::assertSame(CreditPackBadge::MOST_POPULAR, $packs[1]->getBadge());
        self::assertNull($packs[0]->getBadge());
        self::assertNull($packs[2]->getBadge());
    }

    public function testFindActiveOrderedForDisplayExcludesInactivePacks(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $inactive = new CreditPack(credits: 42, priceCents: 4242, currency: 'usd', badge: null, displayOrder: 99, active: false);
        $entityManager->persist($inactive);
        $entityManager->flush();

        $repository = $container->get(CreditPackRepository::class);
        $credits = array_map(static fn (CreditPack $pack) => $pack->getCredits(), $repository->findActiveOrderedForDisplay());

        self::assertNotContains(42, $credits);
    }

    public function testFindOneActiveByPublicIdReturnsAnActivePack(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $repository = $container->get(CreditPackRepository::class);

        $pack = $repository->findActiveOrderedForDisplay()[0];

        self::assertSame($pack, $repository->findOneActiveByPublicId((string) $pack->getPublicId()));
    }

    public function testFindOneActiveByPublicIdReturnsNullForAnInactivePack(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $inactive = new CreditPack(credits: 7, priceCents: 700, currency: 'usd', badge: null, displayOrder: 100, active: false);
        $entityManager->persist($inactive);
        $entityManager->flush();

        $repository = $container->get(CreditPackRepository::class);

        self::assertNull($repository->findOneActiveByPublicId((string) $inactive->getPublicId()));
    }

    public function testFindOneActiveByPublicIdReturnsNullForAMalformedId(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(CreditPackRepository::class);

        self::assertNull($repository->findOneActiveByPublicId('not-a-uuid'));
    }

    protected function tearDown(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Seule cette suite crée des packs inactifs — la grille de lancement (migration) est
        // entièrement active, donc ce nettoyage ne peut jamais toucher les données seedées.
        foreach ($entityManager->getRepository(CreditPack::class)->findBy(['active' => false]) as $pack) {
            $entityManager->remove($pack);
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
