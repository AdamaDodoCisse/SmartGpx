<?php

declare(strict_types=1);

namespace App\Identity\Action;

use App\Identity\Entity\User;
use App\Identity\Event\UserRegisteredEvent;
use App\Identity\Exception\EmailAlreadyUsedException;
use App\Identity\Repository\UserRepository;
use App\Identity\Request\RegisterUserRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RegisterUserAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws EmailAlreadyUsedException si un compte existe déjà pour cet e-mail
     */
    public function execute(RegisterUserRequest $request): User
    {
        if (null !== $this->userRepository->findOneByEmail($request->email)) {
            throw new EmailAlreadyUsedException($request->email);
        }

        $user = new User($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));

        return $user;
    }
}
