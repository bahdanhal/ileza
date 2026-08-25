<?php

declare(strict_types=1);

namespace App\Market\Infrastructure\Mail;

use App\Market\Application\PriceAlertMailerInterface;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class TransactionalPriceAlertMailer implements PriceAlertMailerInterface
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private string $senderEmail = 'powiadomienia@ileza.pl',
        private string $senderName = 'IleZa.pl',
        private string $baseUrl = 'https://ileza.pl',
        private string $logDirectory = '',
        private ?SmtpTransportInterface $smtpTransport = null,
    ) {
    }

    public function sendVerificationEmail(PriceAlert $alert, Product $product): bool
    {
        $locale = $alert->locale !== '' ? $alert->locale : 'pl';
        $verifyPath = $locale === 'pl'
            ? '/ceny/alert/potwierdz/' . $alert->verificationToken
            : '/prices/alert/verify/' . $alert->verificationToken;
        $unsubscribePath = $locale === 'pl'
            ? '/ceny/alert/rezygnuj/' . $alert->unsubscribeToken
            : '/prices/alert/unsubscribe/' . $alert->unsubscribeToken;

        $verifyUrl = rtrim($this->baseUrl, '/') . $verifyPath;
        $unsubscribeUrl = rtrim($this->baseUrl, '/') . $unsubscribePath;
        $productUrl = rtrim($this->baseUrl, '/') . ($locale === 'pl' ? '/ceny/' : '/prices/') . $product->slug;

        $subject = $this->translator->trans('market.mail.verify.subject', [
            '%product%' => $product->name,
        ], 'messages', $locale);

        $context = [
            'alert' => $alert,
            'product' => $product,
            'verify_url' => $verifyUrl,
            'unsubscribe_url' => $unsubscribeUrl,
            'product_url' => $productUrl,
            'locale' => $locale,
            'subject' => $subject,
        ];

        $htmlBody = $this->twig->render('mail/price_alert_verification.html.twig', $context);
        $textBody = $this->twig->render('mail/price_alert_verification.txt.twig', $context);

        return $this->dispatchEmail($alert->email, $subject, $htmlBody, $textBody);
    }

    public function sendPriceDropNotification(
        PriceAlert $alert,
        Product $product,
        PriceObservation $latestObservation,
        ?PriceObservation $previousObservation = null,
    ): bool {
        $locale = $alert->locale !== '' ? $alert->locale : 'pl';
        $unsubscribePath = $locale === 'pl'
            ? '/ceny/alert/rezygnuj/' . $alert->unsubscribeToken
            : '/prices/alert/unsubscribe/' . $alert->unsubscribeToken;

        $unsubscribeUrl = rtrim($this->baseUrl, '/') . $unsubscribePath;
        $productUrl = rtrim($this->baseUrl, '/') . ($locale === 'pl' ? '/ceny/' : '/prices/') . $product->slug;

        $priceDropDiffGrosz = $previousObservation !== null
            ? max(0, $previousObservation->medianGrosz - $latestObservation->medianGrosz)
            : 0;

        $subject = $this->translator->trans('market.mail.drop.subject', [
            '%product%' => $product->name,
            '%price%' => number_format($latestObservation->medianGrosz / 100, 0, ',', ' ') . ' zł',
        ], 'messages', $locale);

        $context = [
            'alert' => $alert,
            'product' => $product,
            'latest' => $latestObservation,
            'previous' => $previousObservation,
            'price_drop_diff_grosz' => $priceDropDiffGrosz,
            'unsubscribe_url' => $unsubscribeUrl,
            'product_url' => $productUrl,
            'locale' => $locale,
            'subject' => $subject,
        ];

        $htmlBody = $this->twig->render('mail/price_alert_notification.html.twig', $context);
        $textBody = $this->twig->render('mail/price_alert_notification.txt.twig', $context);

        return $this->dispatchEmail($alert->email, $subject, $htmlBody, $textBody);
    }

    private function dispatchEmail(string $recipient, string $subject, string $htmlBody, string $textBody): bool
    {
        $smtpSent = false;
        if ($this->smtpTransport !== null) {
            $smtpSent = $this->smtpTransport->send(
                $this->senderEmail,
                $this->senderName,
                $recipient,
                $subject,
                $htmlBody,
                $textBody
            );
        }

        // Safe logging / file persistence for backup audit and environments without SMTP
        if ($this->logDirectory !== '' && is_dir($this->logDirectory)) {
            $logEntry = json_encode([
                'timestamp' => gmdate('c'),
                'from' => sprintf('%s <%s>', $this->senderName, $this->senderEmail),
                'recipient' => $recipient,
                'subject' => $subject,
                'smtp_sent' => $smtpSent,
                'text_preview' => mb_substr($textBody, 0, 200),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents($this->logDirectory . '/dispatched_emails.jsonl', $logEntry, FILE_APPEND | LOCK_EX);
        }

        return $smtpSent || $this->smtpTransport === null;
    }
}
