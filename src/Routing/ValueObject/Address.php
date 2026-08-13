<?php

declare(strict_types=1);

namespace App\Routing\ValueObject;

final class Address implements RouteLocation
{
    private function __construct(
        public readonly string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Address cannot be empty.');
        }

        return new self($trimmed);
    }

    /**
     * @return array{address: string}
     */
    public function toGoogleWaypoint(): array
    {
        return ['address' => $this->value];
    }

    public function label(): string
    {
        return $this->value;
    }
}
