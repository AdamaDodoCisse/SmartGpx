<?php

declare(strict_types=1);

namespace App\Tests\Identity\Request;

use App\Identity\Request\RegisterUserRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterUserRequestTest extends KernelTestCase
{
    public function testAValidRequestHasNoViolations(): void
    {
        $request = new RegisterUserRequest();
        $request->email = 'valid@example.com';
        $request->plainPassword = 'correct-horse-battery-staple';

        self::assertCount(0, $this->validator()->validate($request));
    }

    public function testAnInvalidEmailIsRejected(): void
    {
        $request = new RegisterUserRequest();
        $request->email = 'not-an-email';
        $request->plainPassword = 'correct-horse-battery-staple';

        self::assertGreaterThan(0, \count($this->validator()->validate($request)));
    }

    public function testABlankEmailIsRejected(): void
    {
        $request = new RegisterUserRequest();
        $request->email = '';
        $request->plainPassword = 'correct-horse-battery-staple';

        self::assertGreaterThan(0, \count($this->validator()->validate($request)));
    }

    public function testATooShortPasswordIsRejected(): void
    {
        $request = new RegisterUserRequest();
        $request->email = 'valid@example.com';
        $request->plainPassword = 'short';

        self::assertGreaterThan(0, \count($this->validator()->validate($request)));
    }

    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return static::getContainer()->get(ValidatorInterface::class);
    }
}
