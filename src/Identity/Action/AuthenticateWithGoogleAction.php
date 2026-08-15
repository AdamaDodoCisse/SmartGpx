<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Entity\User;
use App\Identity\Enum\AuthProvider;
use App\Identity\Event\UserRegisteredEvent;
use App\Identity\Exception\GoogleEmailNotVerifiedException;
use App\Identity\Repository\UserRepository;
use App\Identity\ValueObject\GoogleIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AuthenticateWithGoogleAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws GoogleEmailNotVerifiedException si un compte local existe pour cette adresse mais
     *                                         que Google ne la rapporte pas comme vérifiée
     */
    public function execute(GoogleIdentity $identity): User
    {
        $existingByGoogleId = $this->userRepository->findOneByGoogleId($identity->googleId);
        if (null !== $existingByGoogleId) {
            return $existingByGoogleId;
        }

        $existingByEmail = $this->userRepository->findOneByEmail($identity->email);
        if (null !== $existingByEmail) {
            if (!$identity->emailVerified) {
                throw new GoogleEmailNotVerifiedException($identity->email);
            }

            $existingByEmail->setGoogleId($identity->googleId);
            $this->entityManager->flush();

            return $existingByEmail;
        }

        $user = new User($identity->email);
        $user->setGoogleId($identity->googleId);
        $user->setAuthProvider(AuthProvider::GOOGLE);
        $user->setVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));

        return $user;
    }
}
