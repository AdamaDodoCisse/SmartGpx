<?php

declare(strict_types=1);

namespace App\Identity\Exception;

final class EmailAlreadyUsedException extends \RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('An account already exists for email "%s".', $email));
    }
}
