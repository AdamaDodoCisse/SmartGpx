<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Action;

use App\Conversion\Action\ConvertGoogleMapsToGpxAction;
use App\Conversion\Repository\ConversionRepository;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailNotVerifiedException;
use App\Identity\Repository\UserRepository;
use App\Routing\Enum\RoutingFeatureCostTier;
use App\Routing\Enum\RoutingPreference;
use App\Routing\Enum\TravelMode;
use App\Routing\Enum\WaypointType;
use App\Routing\Exception\RouteNotFoundException;
use App\Routing\Provider\FakeRoutingProvider;
use App\Routing\Result\RouteComputation;
use App\Routing\ValueObject\RouteModifiers;
use App\Routing\ValueObject\RouteOptions;
use App\Usage\Entity\CreditAccount;
use App\Usage\Enum\CreditTransactionType;
use App\Usage\Exception\InsufficientCreditsException;
use App\Usage\Repository\CreditAccountRepository;
use App\Usage\Repository\CreditTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ConvertGoogleMapsToGpxActionTest extends KernelTestCase
{
    private const string VALID_URL = 'https://www.google.com/maps/dir/?api=1&origin=Cergy%2C+France&destination=Paris%2C+France&travelmode=driving';

    private EntityManagerInterface $entityManager;
    private ConvertGoogleMapsToGpxAction $action;
    private FakeRoutingProvider $fakeRoutingProvider;
    private CreditAccountRepository $creditAccountRepository;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(ConvertGoogleMapsToGpxAction::class);
        $this->fakeRoutingProvider = $container->get(FakeRoutingProvider::class);
        $this->fakeRoutingProvider->reset();
        $this->creditAccountRepository = $container->get(CreditAccountRepository::class);

        $this->user = new User(sprintf('convert-action-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->user->setVerified(true);
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testASuccessfulConversionPersistsAConversionAndConsumesOneCredit(): void
    {
        $this->seedAccount(1);

        $conversion = $this->action->execute($this->user, self::VALID_URL);

        self::assertSame('Cergy, France', $conversion->getOriginLabel());
        self::assertSame('Paris, France', $conversion->getDestinationLabel());
        self::assertGreaterThan(0, $conversion->getTrackPointCount());
        self::assertSame(1, $this->fakeRoutingProvider->callCount);

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(0, $account->getBalance());
        self::assertSame(0, $account->getReserved());

        $creditTransactionRepository = static::getContainer()->get(CreditTransactionRepository::class);
        $transactions = $creditTransactionRepository->findBy(['creditAccount' => $account]);
        self::assertCount(1, $transactions);
        self::assertSame(CreditTransactionType::CONVERSION, $transactions[0]->getType());
        self::assertSame(-1, $transactions[0]->getAmount());
        self::assertSame($conversion->getId(), $transactions[0]->getConversionId());
    }

    public function testARoutingFailureReleasesTheReservationAndChargesNothing(): void
    {
        $this->seedAccount(1);
        $this->fakeRoutingProvider->queue(new RouteNotFoundException('No route.'));

        try {
            $this->action->execute($this->user, self::VALID_URL);
            self::fail('Expected RouteNotFoundException was not thrown.');
        } catch (RouteNotFoundException) {
            // expected
        }

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(1, $account->getBalance(), 'A failed conversion must cost 0 credits.');
        self::assertSame(0, $account->getReserved());

        $creditTransactionRepository = static::getContainer()->get(CreditTransactionRepository::class);
        self::assertCount(0, $creditTransactionRepository->findBy(['creditAccount' => $account]));

        $conversionRepository = static::getContainer()->get(ConversionRepository::class);
        self::assertCount(0, $conversionRepository->findBy(['user' => $this->user]));
    }

    public function testAnUnverifiedEmailPreventsAnyCreditReservation(): void
    {
        $this->user->setVerified(false);
        $this->entityManager->flush();
        $this->seedAccount(1);

        try {
            $this->action->execute($this->user, self::VALID_URL);
            self::fail('Expected EmailNotVerifiedException was not thrown.');
        } catch (EmailNotVerifiedException) {
            // expected
        }

        self::assertSame(0, $this->fakeRoutingProvider->callCount, 'The routing provider must never be called for an unverified email.');

        $account = $this->creditAccountRepository->findOneByUserOrFail($this->user);
        $this->entityManager->refresh($account);
        self::assertSame(1, $account->getBalance(), 'No credit should be reserved for an unverified email.');
        self::assertSame(0, $account->getReserved());
    }

    public function testInsufficientCreditsPreventsAnyExternalCall(): void
    {
        $this->seedAccount(0);

        try {
            $this->action->execute($this->user, self::VALID_URL);
            self::fail('Expected InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException) {
            // expected
        }

        self::assertSame(0, $this->fakeRoutingProvider->callCount, 'The routing provider must never be called without an available credit.');
    }

    public function testAdvancedRouteOptionsAreStoredOnTheConversion(): void
    {
        $this->seedAccount(1);
        $options = new RouteOptions(
            routingPreference: RoutingPreference::TRAFFIC_AWARE,
            modifiers: new RouteModifiers(avoidHighways: true, avoidTolls: true),
        );

        $conversion = $this->action->execute($this->user, self::VALID_URL, null, $options);

        $stored = $conversion->getRouteOptions();
        self::assertTrue($stored['avoidHighways']);
        self::assertTrue($stored['avoidTolls']);
        self::assertFalse($stored['avoidFerries']);
        self::assertSame('TRAFFIC_AWARE', $stored['routingPreference']);
        self::assertSame(RoutingFeatureCostTier::STANDARD, $conversion->getCostTier());
    }

    public function testRequestingAlternativesClassifiesTheConversionAsAdvanced(): void
    {
        $this->seedAccount(1);
        $options = new RouteOptions(computeAlternativeRoutes: true);
        $this->fakeRoutingProvider->queue(new RouteComputation(
            [FakeRoutingProvider::defaultFixtureRoute(TravelMode::DRIVE)],
            RoutingFeatureCostTier::ADVANCED,
        ));

        $conversion = $this->action->execute($this->user, self::VALID_URL, null, $options);

        self::assertSame(RoutingFeatureCostTier::ADVANCED, $conversion->getCostTier());
    }

    public function testWaypointTypesAreStoredAlignedWithIntermediates(): void
    {
        $this->seedAccount(1);
        $urlWithStops = 'https://www.google.com/maps/dir/?api=1&origin=Paris%2C+France&destination=Lyon%2C+France&waypoints=Orl%C3%A9ans%2C+France%7CTours%2C+France&travelmode=driving';

        $conversion = $this->action->execute($this->user, $urlWithStops, null, new RouteOptions(), [WaypointType::STOP, WaypointType::VIA]);

        self::assertSame(['STOP', 'VIA'], $conversion->getWaypointTypes());
    }

    public function testCoordinateWaypointNeverBecomesAnAddressThroughTheFullPipeline(): void
    {
        $this->seedAccount(1);
        $urlWithCoordinateWaypoint = 'https://www.google.com/maps/dir/?api=1&origin=Paris%2C+France&destination=49.051624%2C2.0093594&travelmode=driving';

        $this->fakeRoutingProvider->queue(new RouteComputation(
            [FakeRoutingProvider::defaultFixtureRoute(TravelMode::DRIVE)],
            RoutingFeatureCostTier::STANDARD,
        ));

        $conversion = $this->action->execute($this->user, $urlWithCoordinateWaypoint, null, new RouteOptions());

        self::assertSame('49.051624, 2.0093594', $conversion->getDestinationLabel());
    }

    private function seedAccount(int $balance): void
    {
        $account = new CreditAccount($this->user);
        $account->initializeBalance($balance);
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($container->get(ConversionRepository::class)->findAll() as $conversion) {
            $entityManager->remove($conversion);
        }
        $entityManager->flush();

        foreach ($container->get(CreditTransactionRepository::class)->findAll() as $transaction) {
            $entityManager->remove($transaction);
        }
        $entityManager->flush();

        foreach ($container->get(CreditAccountRepository::class)->findAll() as $account) {
            $entityManager->remove($account);
        }
        $entityManager->flush();

        $userRepository = $container->get(UserRepository::class);
        foreach ($userRepository->findAll() as $user) {
            if (str_contains($user->getEmail(), '@example.com')) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
