<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

final class RouteLocationParser
{
    public function parse(string $raw): RouteLocation
    {
        return Coordinates::tryParse($raw) ?? Address::fromString($raw);
    }
}
