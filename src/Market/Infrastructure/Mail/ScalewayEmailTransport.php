<?php

declare(strict_types=1);

namespace App\Market\Infrastructure\Mail;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ScalewayEmailTransport implements EmailTransportInterface
{
    /** @var list<array{from_email: string, from_name: string, to_email: string, subject: string, html_body: string, text_body: string, sent_at: int}> */
    private static array $mockSent = [];

    private readonly string $secretKey;
    private readonly string $projectId;
    private readonly string $region;

    public function __construct(
        ?string $secretKey = null,
        ?string $projectId = null,
        ?string $region = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->secretKey = $secretKey ?? '';
        $this->projectId = $projectId ?? '';
        $this->region = $region ?? 'fr-par';
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): bool {
        $cleanFromEmail = str_replace(["\r", "\n", "\0", '<', '>'], '', trim($fromEmail));
        $cleanToEmail = str_replace(["\r", "\n", "\0", '<', '>'], '', strtolower(trim($toEmail)));

        if (
            filter_var($cleanFromEmail, FILTER_VALIDATE_EMAIL) === false
            || filter_var($cleanToEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            $this->logger->warning(sprintf('[ScalewayEmailTransport] Invalid email address: from="%s", to="%s"', $cleanFromEmail, $cleanToEmail));
            return false;
        }

        $cleanFromName = str_replace(["\r", "\n", "\0"], '', trim($fromName));
        $cleanSubject = str_replace(["\r", "\n", "\0"], '', trim($subject));

        // In test environment or when credentials are not configured, simulate delivery
        if (
            ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev') === 'test'
            || $this->secretKey === ''
            || $this->projectId === ''
        ) {
            self::$mockSent[] = [
                'from_email' => $cleanFromEmail,
                'from_name' => $cleanFromName,
                'to_email' => $cleanToEmail,
                'subject' => $cleanSubject,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
                'sent_at' => time(),
            ];
            $this->logger->info(sprintf('[Mock Email] Scaleway delivery to %s: %s', $cleanToEmail, $cleanSubject));
            return true;
        }

        $payload = [
            'project_id' => $this->projectId,
            'from' => [
                'email' => $cleanFromEmail,
                'name' => $cleanFromName,
            ],
            'to' => [
                ['email' => $cleanToEmail],
            ],
            'subject' => $cleanSubject,
            'text' => $textBody,
            'html' => $htmlBody,
        ];

        return $this->dispatchViaScalewayApi($payload);
    }

    /** @param array<string, mixed> $payload */
    private function dispatchViaScalewayApi(array $payload): bool
    {
        $url = sprintf('https://api.scaleway.com/transactional-email/v1alpha1/regions/%s/emails', rawurlencode($this->region));
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Auth-Token: ' . $this->secretKey,
                ],
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            if ($curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(sprintf(
                    'Failed to send email via Scaleway TEM: status=%d, error=%s, response=%s',
                    $statusCode,
                    $curlError,
                    is_string($response) ? substr($response, 0, 500) : ''
                ));
                return false;
            }

            return true;
        }

        // Fallback using streams if curl is not available
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "X-Auth-Token: {$this->secretKey}\r\n",
                'content' => $jsonPayload,
                'timeout' => 6,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    }

    /** @return list<array{from_email: string, from_name: string, to_email: string, subject: string, html_body: string, text_body: string, sent_at: int}> */
    public static function getMockSent(): array
    {
        return self::$mockSent;
    }

    public static function clearMockSent(): void
    {
        self::$mockSent = [];
    }
}
