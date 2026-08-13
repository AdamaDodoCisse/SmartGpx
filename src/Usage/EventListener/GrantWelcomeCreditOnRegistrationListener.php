<?php

declare(strict_types=1);

namespace App\Usage\EventListener;

use App\Identity\Event\UserRegisteredEvent;
use App\Usage\Action\GrantWelcomeCreditAction;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UserRegisteredEvent::class)]
final class GrantWelcomeCreditOnRegistrationListener
{
    public function __construct(
        private readonly GrantWelcomeCreditAction $grantWelcomeCreditAction,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->grantWelcomeCreditAction->execute($event->user);
    }
}
