<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\Mail\SmtpTransport;
use PHPUnit\Framework\TestCase;

final class SmtpTransportTest extends TestCase
{
    public function testSendReturnsFalseWhenHostIsEmpty(): void
    {
        $transport = new SmtpTransport(host: '');

        $result = $transport->send(
            fromEmail: 'powiadomienia@ileza.pl',
            fromName: 'IleZa.pl',
            toEmail: 'user@example.com',
            subject: 'Test Subject',
            htmlBody: '<p>Test</p>',
            textBody: 'Test'
        );

        $this->assertFalse($result);
    }

    public function testSendHandlesUnreachableHostGracefully(): void
    {
        $transport = new SmtpTransport(
            host: '127.0.0.1',
            port: 65534, // Unreachable port
            timeout: 1
        );

        $result = $transport->send(
            fromEmail: 'powiadomienia@ileza.pl',
            fromName: 'IleZa.pl',
            toEmail: 'user@example.com',
            subject: 'Test Subject',
            htmlBody: '<p>Test</p>',
            textBody: 'Test'
        );

        $this->assertFalse($result);
    }

    public function testSendRejectsEmailsWithCrlfOrInvalidFormat(): void
    {
        $transport = new SmtpTransport(host: '127.0.0.1', port: 587);

        $resultWithCrlf = $transport->send(
            fromEmail: "attacker@example.com\r\nRCPT TO:<victim@example.com>",
            fromName: "Attacker\r\nInjection",
            toEmail: 'user@example.com',
            subject: "Alert\r\nBcc: spy@example.com",
            htmlBody: '<p>Test</p>',
            textBody: 'Test'
        );
        $this->assertFalse($resultWithCrlf);

        $resultWithInvalidEmail = $transport->send(
            fromEmail: 'not-an-email',
            fromName: 'IleZa.pl',
            toEmail: 'user@example.com',
            subject: 'Test',
            htmlBody: '<p>Test</p>',
            textBody: 'Test'
        );
        $this->assertFalse($resultWithInvalidEmail);
    }
}
