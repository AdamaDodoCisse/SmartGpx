<?php

declare(strict_types=1);

namespace App\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class NewPasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $plainPassword = '';
}
