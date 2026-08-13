<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class EmailLinkExtractor
{
    /**
     * Extrait le premier lien href="..." du corps HTML d'un e-mail de test.
     */
    public static function firstLink(?RawMessage $message): string
    {
        Assert::assertInstanceOf(Email::class, $message);

        $htmlBody = $message->getHtmlBody();
        Assert::assertIsString($htmlBody);

        $found = preg_match('/href="([^"]+)"/', $htmlBody, $matches);
        Assert::assertSame(1, $found, 'The email should contain a link.');

        return html_entity_decode($matches[1]);
    }
}
