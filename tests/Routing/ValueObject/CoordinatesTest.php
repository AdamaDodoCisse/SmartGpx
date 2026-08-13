<?php

declare(strict_types=1);

namespace App\Tests\Routing\ValueObject;

use App\Routing\ValueObject\Coordinates;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    public function testFromValuesRejectsOutOfRangeLatitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Coordinates::fromValues(91.0, 2.0);
    }

    public function testFromValuesRejectsOutOfRangeLongitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Coordinates::fromValues(45.0, 181.0);
    }

    public function testFromValuesAcceptsBoundaryValues(): void
    {
        $coordinates = Coordinates::fromValues(90.0, 180.0);

        self::assertSame(90.0, $coordinates->latitude);
        self::assertSame(180.0, $coordinates->longitude);
    }

    public function testLabelIsHumanReadable(): void
    {
        $coordinates = Coordinates::fromValues(49.051624, 2.0093594);

        self::assertSame('49.051624, 2.0093594', $coordinates->label());
    }
}
