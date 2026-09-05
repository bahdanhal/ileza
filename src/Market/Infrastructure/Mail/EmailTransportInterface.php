<?php

declare(strict_types=1);

namespace App\Market\Infrastructure\Mail;

interface EmailTransportInterface
{
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): bool;
}
