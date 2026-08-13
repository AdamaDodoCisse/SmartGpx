<?php

declare(strict_types=1);

namespace App\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgotPasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
