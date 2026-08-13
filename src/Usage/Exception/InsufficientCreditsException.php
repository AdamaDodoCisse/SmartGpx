<?php

declare(strict_types=1);

namespace App\Usage\Exception;

use App\Identity\Entity\User;

final class InsufficientCreditsException extends \RuntimeException
{
    public function __construct(User $user)
    {
        parent::__construct(sprintf('User "%s" has insufficient credits.', $user->getPublicId()));
    }
}
