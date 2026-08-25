<?php

declare(strict_types=1);

namespace App\Market\Domain;

interface PriceAlertRepository
{
    public function subscribe(
        string $productSlug,
        string $email,
        ?int $targetPriceGrosz,
        string $ipAddress,
        string $locale = 'pl',
    ): PriceAlert;

    public function findByVerificationToken(string $token): ?PriceAlert;

    public function findByUnsubscribeToken(string $token): ?PriceAlert;

    public function markVerified(string $verificationToken): ?PriceAlert;

    public function markUnsubscribed(string $unsubscribeToken): ?PriceAlert;

    /**
     * @return list<PriceAlert>
     */
    public function findActiveForProduct(string $productSlug): array;

    /**
     * @return list<PriceAlert>
     */
    public function all(): array;

    public function updateNotificationState(int $alertId, \DateTimeImmutable $notifiedAt, int $notifiedPriceGrosz, bool $flush = true): void;

    public function flush(): void;

    /**
     * @return array{total: int, active: int, pending: int, unsubscribed: int}
     */
    public function countStatistics(): array;
}
