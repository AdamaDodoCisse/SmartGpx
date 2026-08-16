<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Entity\CreditPack;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PricingControllerTest extends WebTestCase
{
    public function testItRendersOnlyActivePacksInDisplayOrder(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $inactive = new CreditPack(credits: 5, priceCents: 500, currency: 'usd', badge: null, displayOrder: 0, active: false);
        $entityManager->persist($inactive);
        $entityManager->flush();

        $crawler = $client->request('GET', '/pricing');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('$5.00', $body);

        $prices = $crawler->filter('.text-2xl.font-semibold')->each(static fn ($node) => $node->text());
        self::assertSame(['$4.99', '$9.99', '$29.99'], $prices);

        $entityManager->remove($inactive);
        $entityManager->flush();
    }
}
