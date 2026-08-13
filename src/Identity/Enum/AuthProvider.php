<?php

declare(strict_types=1);

namespace App\Identity\Enum;

enum AuthProvider: string
{
    case LOCAL = 'local';
    case GOOGLE = 'google';
}
