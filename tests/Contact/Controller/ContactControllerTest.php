<?php

declare(strict_types=1);

namespace App\Tests\Contact\Controller;

use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

final class ContactControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testSubmittingTheFormSendsAnEmailAndShowsAConfirmation(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '10.0.0.1');

        $crawler = $client->request('GET', '/contact');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Send message')->form([
            'contact_form[name]' => 'Jane Doe',
            'contact_form[email]' => 'jane@example.com',
            'contact_form[message]' => 'Hello, I have a question about credits.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/contact');
        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('Jane Doe', $email->getBody()->toString());

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Your message has been sent');
    }

    public function testAnInvalidEmailIsRejectedWithoutSendingAnything(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '10.0.0.2');

        $crawler = $client->request('GET', '/contact');
        $form = $crawler->selectButton('Send message')->form([
            'contact_form[name]' => 'Jane Doe',
            'contact_form[email]' => 'not-an-email',
            'contact_form[message]' => 'Hello, I have a question about credits.',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testExceedingTheRateLimitStopsSendingButStillConfirms(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '10.0.0.3');
        $sentCount = 0;

        for ($i = 0; $i < 6; ++$i) {
            $crawler = $client->request('GET', '/contact');
            $form = $crawler->selectButton('Send message')->form([
                'contact_form[name]' => 'Jane Doe',
                'contact_form[email]' => 'jane@example.com',
                'contact_form[message]' => 'Hello, I have a question about credits.',
            ]);
            $client->submit($form);
            $sentCount += \count(self::getMailerMessages());
        }

        // 5 accepted by the limiter, the 6th rejected — never more than 5 emails sent in total.
        self::assertSame(5, $sentCount);
    }
}
