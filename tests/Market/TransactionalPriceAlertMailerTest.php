<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Infrastructure\Mail\SmtpTransportInterface;
use App\Market\Infrastructure\Mail\TransactionalPriceAlertMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TransactionalPriceAlertMailerTest extends TestCase
{
    public function testSendVerificationEmailUsesTwigAndSmtpTransport(): void
    {
        $loader = new ArrayLoader([
            'mail/price_alert_verification.html.twig' => '<h1>Verify {{ product.name }}</h1><a href="{{ verify_url }}">Link</a>',
            'mail/price_alert_verification.txt.twig' => 'Verify {{ product.name }}: {{ verify_url }}',
        ]);
        $twig = new Environment($loader);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Potwierdź subskrypcję');

        $transport = $this->createMock(SmtpTransportInterface::class);
        $transport->expects(self::once())
            ->method('send')
            ->with(
                'powiadomienia@ileza.pl',
                'IleZa.pl',
                'buyer@example.com',
                'Potwierdź subskrypcję',
                self::stringContains('<h1>Verify Apple iPhone 14</h1>'),
                self::stringContains('Verify Apple iPhone 14:')
            )
            ->willReturn(true);

        $logDir = sys_get_temp_dir() . '/ileza_mail_test_' . uniqid();
        mkdir($logDir, 0777, true);

        $mailer = new TransactionalPriceAlertMailer(
            twig: $twig,
            translator: $translator,
            senderEmail: 'powiadomienia@ileza.pl',
            senderName: 'IleZa.pl',
            baseUrl: 'https://ileza.pl',
            logDirectory: $logDir,
            smtpTransport: $transport
        );

        $alert = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'buyer@example.com',
            targetPriceGrosz: 240000,
            verificationToken: 'verify123',
            unsubscribeToken: 'unsub123',
            isVerified: false,
            verifiedAt: null,
            ipHash: 'hash',
            locale: 'pl',
            createdAt: new \DateTimeImmutable()
        );

        $product = new Product(
            slug: 'iphone-14-128gb',
            name: 'Apple iPhone 14',
            definition: 'Exact product identity',
            category: 'smartphones',
            familySlug: 'iphone-14',
            familyName: 'Apple iPhone 14',
            image: '',
            specifications: []
        );

        $result = $mailer->sendVerificationEmail($alert, $product);
        $this->assertTrue($result);
        $this->assertFileExists($logDir . '/dispatched_emails.jsonl');
    }

    public function testSendPriceDropNotificationRendersTemplateAndDispatches(): void
    {
        $loader = new ArrayLoader([
            'mail/price_alert_notification.html.twig' => '<h1>Price drop for {{ product.name }}</h1><p>{{ latest.medianGrosz }}</p>',
            'mail/price_alert_notification.txt.twig' => 'Price drop for {{ product.name }}: {{ latest.medianGrosz }}',
        ]);
        $twig = new Environment($loader);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Spadek ceny');

        $transport = $this->createMock(SmtpTransportInterface::class);
        $transport->expects(self::once())
            ->method('send')
            ->willReturn(true);

        $mailer = new TransactionalPriceAlertMailer(
            twig: $twig,
            translator: $translator,
            senderEmail: 'powiadomienia@ileza.pl',
            senderName: 'IleZa.pl',
            baseUrl: 'https://ileza.pl',
            smtpTransport: $transport
        );

        $alert = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'subscriber@example.com',
            targetPriceGrosz: 250000,
            verificationToken: 'vtok',
            unsubscribeToken: 'utok',
            isVerified: true,
            verifiedAt: new \DateTimeImmutable(),
            ipHash: 'hash',
            locale: 'pl',
            createdAt: new \DateTimeImmutable()
        );

        $product = new Product(
            slug: 'iphone-14-128gb',
            name: 'Apple iPhone 14',
            definition: 'Exact product identity',
            category: 'smartphones',
            familySlug: 'iphone-14',
            familyName: 'Apple iPhone 14',
            image: '',
            specifications: []
        );

        $latest = new PriceObservation(
            productSlug: 'iphone-14-128gb',
            observedAt: new \DateTimeImmutable(),
            medianGrosz: 230000,
            lowGrosz: 210000,
            highGrosz: 250000,
            availability: 'available',
            summary: '',
            methodology: PriceObservation::METHODOLOGY_MANUAL
        );

        $result = $mailer->sendPriceDropNotification($alert, $product, $latest);
        $this->assertTrue($result);
    }
}
