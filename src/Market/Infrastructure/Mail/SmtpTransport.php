<?php

declare(strict_types=1);

namespace App\Market\Infrastructure\Mail;

final readonly class SmtpTransport implements SmtpTransportInterface
{
    public function __construct(
        private string $host = '',
        private int $port = 587,
        private string $username = '',
        private string $password = '',
        private string $encryption = 'tls',
        private int $timeout = 10,
    ) {
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): bool {
        if ($this->host === '') {
            return false;
        }

        $remoteSocket = sprintf('tcp://%s:%d', $this->host, $this->port);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            (float) $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            return false;
        }

        try {
            stream_set_timeout($socket, $this->timeout);

            // 1. Initial greeting
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '220')) {
                return false;
            }

            // 2. EHLO
            $clientDomain = gethostname() !== false ? gethostname() : 'ileza.pl';
            $this->sendCommand($socket, sprintf('EHLO %s', $clientDomain));
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '250')) {
                return false;
            }

            // 3. STARTTLS if required / supported
            if (in_array(strtolower($this->encryption), ['tls', 'starttls'], true) || $this->port === 587) {
                $this->sendCommand($socket, 'STARTTLS');
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '220')) {
                    return false;
                }

                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                if ($cryptoOk !== true) {
                    return false;
                }

                // Resend EHLO over TLS
                $this->sendCommand($socket, sprintf('EHLO %s', $clientDomain));
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '250')) {
                    return false;
                }
            }

            // 4. Authenticate if username is provided
            if ($this->username !== '') {
                $this->sendCommand($socket, 'AUTH LOGIN');
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '334')) {
                    return false;
                }

                $this->sendCommand($socket, base64_encode($this->username));
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '334')) {
                    return false;
                }

                $this->sendCommand($socket, base64_encode($this->password));
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '235')) {
                    return false;
                }
            }

            // 5. MAIL FROM
            $this->sendCommand($socket, sprintf('MAIL FROM:<%s>', $fromEmail));
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '250')) {
                return false;
            }

            // 6. RCPT TO
            $this->sendCommand($socket, sprintf('RCPT TO:<%s>', $toEmail));
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '250') && !str_starts_with($response, '251')) {
                return false;
            }

            // 7. DATA
            $this->sendCommand($socket, 'DATA');
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '354')) {
                return false;
            }

            // 8. Send MIME payload
            $boundary = '=_ileza_' . md5(uniqid((string) mt_rand(), true));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $messageId = sprintf('<%s.%s@ileza.pl>', uniqid('alert_', true), bin2hex(random_bytes(4)));
            $dateHeader = gmdate('r');

            $headers = [
                sprintf('From: %s <%s>', $encodedFromName, $fromEmail),
                sprintf('To: <%s>', $toEmail),
                sprintf('Subject: %s', $encodedSubject),
                sprintf('Date: %s', $dateHeader),
                sprintf('Message-ID: %s', $messageId),
                'MIME-Version: 1.0',
                sprintf('Content-Type: multipart/alternative; boundary="%s"', $boundary),
            ];

            $body = implode("\r\n", $headers) . "\r\n\r\n";
            // Plain text part
            $body .= sprintf("--%s\r\n", $boundary);
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($textBody)) . "\r\n";

            // HTML part
            $body .= sprintf("--%s\r\n", $boundary);
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            $body .= sprintf("--%s--\r\n", $boundary);
            $body .= "\r\n.";

            $this->sendCommand($socket, $body);
            $response = $this->readResponse($socket);
            if (!str_starts_with($response, '250')) {
                return false;
            }

            // 9. QUIT
            $this->sendCommand($socket, 'QUIT');
            $this->readResponse($socket);

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * @param resource $socket
     */
    private function readResponse($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            // Multiline responses have hyphen after code, e.g. "250-SIZE"
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}
