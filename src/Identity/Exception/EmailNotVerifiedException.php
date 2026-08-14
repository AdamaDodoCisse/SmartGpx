<?php

declare(strict_types=1);

namespace App\Identity\Exception;

use App\Identity\Entity\User;

final class EmailNotVerifiedException extends \RuntimeException
{
    public function __construct(User $user)
    {
        parent::__construct(sprintf('User "%s" has not verified their email.', $user->getPublicId()));
    }
}
