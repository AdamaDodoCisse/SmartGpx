<?php

declare(strict_types=1);

namespace App\Tests\Extension\Entity;

use App\Extension\Entity\ExtensionAuthorization;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;

final class ExtensionAuthorizationTest extends TestCase
{
    public function testANewAuthorizationIsNotRevoked(): void
    {
        $authorization = new ExtensionAuthorization(new User('user@example.com'), 'a-hash', 'Chrome extension');

        self::assertFalse($authorization->isRevoked());
        self::assertNull($authorization->getRevokedAt());
        self::assertSame('Chrome extension', $authorization->getLabel());
    }

    public function testRevokeSetsRevokedAt(): void
    {
        $authorization = new ExtensionAuthorization(new User('user@example.com'), 'a-hash');

        $authorization->revoke();

        self::assertTrue($authorization->isRevoked());
        self::assertNotNull($authorization->getRevokedAt());
    }

    public function testRevokeIsIdempotent(): void
    {
        $authorization = new ExtensionAuthorization(new User('user@example.com'), 'a-hash');

        $authorization->revoke();
        $firstRevokedAt = $authorization->getRevokedAt();

        $authorization->revoke();

        self::assertSame($firstRevokedAt, $authorization->getRevokedAt());
    }

    public function testTouchLastUsedAtSetsATimestamp(): void
    {
        $authorization = new ExtensionAuthorization(new User('user@example.com'), 'a-hash');
        self::assertNull($authorization->getLastUsedAt());

        $authorization->touchLastUsedAt();

        self::assertNotNull($authorization->getLastUsedAt());
    }
}
