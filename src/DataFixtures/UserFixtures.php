<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Identity\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public const string VERIFIED_USER_EMAIL = 'verified-user@example.com';
    public const string VERIFIED_USER_PASSWORD = 'correct-horse-battery-staple';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User(self::VERIFIED_USER_EMAIL);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::VERIFIED_USER_PASSWORD));
        $user->setVerified(true);

        $manager->persist($user);
        $manager->flush();
    }
}
