<?php

declare(strict_types=1);

namespace App\Identity\Event;

use App\Identity\Entity\User;

/**
 * Identity ignore délibérément l'existence du domaine Usage (voir architecture.md) : ce
 * domaine ne fait qu'annoncer qu'un utilisateur s'est inscrit, sans savoir qui écoute — le
 * domaine Usage y réagit pour créditer le crédit de bienvenue (GrantWelcomeCreditOnRegistrationListener).
 */
final readonly class UserRegisteredEvent
{
    public function __construct(
        public User $user,
    ) {
    }
}
