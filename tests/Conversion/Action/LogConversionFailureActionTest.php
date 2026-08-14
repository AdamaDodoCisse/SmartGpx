<?php

declare(strict_types=1);

namespace App\Tests\Conversion\Action;

use App\Conversion\Action\LogConversionFailureAction;
use App\Conversion\Enum\ConversionFailureReason;
use App\Conversion\Repository\ConversionFailureRepository;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LogConversionFailureActionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private LogConversionFailureAction $action;
    private ConversionFailureRepository $conversionFailureRepository;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->action = $container->get(LogConversionFailureAction::class);
        $this->conversionFailureRepository = $container->get(ConversionFailureRepository::class);

        $this->user = new User(sprintf('log-failure-%s@example.com', uniqid()));
        $this->user->setPassword('irrelevant-hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testItPersistsExactlyOneFailureWithTheGivenReason(): void
    {
        $this->action->execute($this->user, 'https://example.com/not-google-maps', ConversionFailureReason::UNSUPPORTED_URL);

        $failures = $this->conversionFailureRepository->findAll();
        self::assertCount(1, $failures);
        self::assertSame($this->user->getId(), $failures[0]->getUser()->getId());
        self::assertSame('https://example.com/not-google-maps', $failures[0]->getSourceUrl());
        self::assertSame(ConversionFailureReason::UNSUPPORTED_URL, $failures[0]->getReason());
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach ($this->conversionFailureRepository->findAll() as $failure) {
            $entityManager->remove($failure);
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
