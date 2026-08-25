<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class PriceAlert
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public function __construct(
        public ?int $id,
        public string $productSlug,
        public string $email,
        public ?int $targetPriceGrosz,
        public string $verificationToken,
        public string $unsubscribeToken,
        public bool $isVerified,
        public ?\DateTimeImmutable $verifiedAt,
        public string $ipHash,
        public string $locale,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastNotifiedAt = null,
        public ?int $lastNotifiedPriceGrosz = null,
        public string $status = self::STATUS_PENDING,
    ) {
    }

    public function isActive(): bool
    {
        return $this->isVerified && $this->status === self::STATUS_ACTIVE;
    }

    public function shouldNotifyForPrice(int $currentMedianGrosz, ?int $previousMedianGrosz = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // If a specific target price is set
        if ($this->targetPriceGrosz !== null && $this->targetPriceGrosz > 0) {
            if ($currentMedianGrosz > $this->targetPriceGrosz) {
                return false;
            }
            // Already notified for this exact or lower price
            if ($this->lastNotifiedPriceGrosz !== null && $currentMedianGrosz >= $this->lastNotifiedPriceGrosz) {
                return false;
            }
            return true;
        }

        // Threshold is "any price drop"
        if ($previousMedianGrosz !== null && $currentMedianGrosz < $previousMedianGrosz) {
            if ($this->lastNotifiedPriceGrosz !== null && $currentMedianGrosz >= $this->lastNotifiedPriceGrosz) {
                return false;
            }
            return true;
        }

        return false;
    }
}
