<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\Mail\ScalewayEmailTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ScalewayEmailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        ScalewayEmailTransport::clearMockSent();
    }

    protected function tearDown(): void
    {
        ScalewayEmailTransport::clearMockSent();
    }

    public function testMockDeliveryInTestEnvironment(): void
    {
        $transport = new ScalewayEmailTransport(
            secretKey: 'mock-secret',
            projectId: 'eed5908f-6ad7-4644-934e-cde61c0ca55a',
            region: 'fr-par',
            logger: new NullLogger()
        );

        $sent = $transport->send(
            fromEmail: 'powiadomienia@mail.ileza.pl',
            fromName: 'IleZa.pl',
            toEmail: 'subscriber@example.com',
            subject: 'Spadek ceny Apple iPhone 14',
            htmlBody: '<h1>Cena spadła!</h1>',
            textBody: 'Cena spadła!'
        );

        $this->assertTrue($sent);
        $mockSent = ScalewayEmailTransport::getMockSent();
        $this->assertCount(1, $mockSent);
        $this->assertSame('powiadomienia@mail.ileza.pl', $mockSent[0]['from_email']);
        $this->assertSame('subscriber@example.com', $mockSent[0]['to_email']);
        $this->assertSame('Spadek ceny Apple iPhone 14', $mockSent[0]['subject']);
        $this->assertSame('<h1>Cena spadła!</h1>', $mockSent[0]['html_body']);
        $this->assertSame('Cena spadła!', $mockSent[0]['text_body']);
    }

    public function testRejectsInvalidEmailAddresses(): void
    {
        $transport = new ScalewayEmailTransport(
            secretKey: 'mock-secret',
            projectId: 'eed5908f-6ad7-4644-934e-cde61c0ca55a',
            region: 'fr-par',
            logger: new NullLogger()
        );

        $this->assertFalse($transport->send(
            fromEmail: 'invalid-from',
            fromName: 'IleZa.pl',
            toEmail: 'subscriber@example.com',
            subject: 'Test',
            htmlBody: 'test',
            textBody: 'test'
        ));

        $this->assertFalse($transport->send(
            fromEmail: 'powiadomienia@mail.ileza.pl',
            fromName: 'IleZa.pl',
            toEmail: 'not-an-email',
            subject: 'Test',
            htmlBody: 'test',
            textBody: 'test'
        ));

        $this->assertEmpty(ScalewayEmailTransport::getMockSent());
    }

    public function testClearMockSentEmptiesStoredMessages(): void
    {
        $transport = new ScalewayEmailTransport();
        $transport->send(
            fromEmail: 'powiadomienia@mail.ileza.pl',
            fromName: 'IleZa.pl',
            toEmail: 'test@example.com',
            subject: 'Hello',
            htmlBody: '<b>Hello</b>',
            textBody: 'Hello'
        );

        $this->assertCount(1, ScalewayEmailTransport::getMockSent());
        ScalewayEmailTransport::clearMockSent();
        $this->assertEmpty(ScalewayEmailTransport::getMockSent());
    }
}
