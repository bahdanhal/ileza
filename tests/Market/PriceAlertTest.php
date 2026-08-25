<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceAlert;
use PHPUnit\Framework\TestCase;

final class PriceAlertTest extends TestCase
{
    public function testIsActiveOnlyWhenVerifiedAndStatusActive(): void
    {
        $now = new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC'));

        $pending = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: 250000,
            verificationToken: 'token123',
            unsubscribeToken: 'unsub123',
            isVerified: false,
            verifiedAt: null,
            ipHash: 'hash123',
            locale: 'pl',
            createdAt: $now,
            status: PriceAlert::STATUS_PENDING,
        );

        $this->assertFalse($pending->isActive());

        $active = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: 250000,
            verificationToken: 'token123',
            unsubscribeToken: 'unsub123',
            isVerified: true,
            verifiedAt: $now,
            ipHash: 'hash123',
            locale: 'pl',
            createdAt: $now,
            status: PriceAlert::STATUS_ACTIVE,
        );

        $this->assertTrue($active->isActive());
    }

    public function testShouldNotifyForTargetPriceThreshold(): void
    {
        $now = new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC'));

        $alert = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: 250000, // 2 500 zł
            verificationToken: 'token123',
            unsubscribeToken: 'unsub123',
            isVerified: true,
            verifiedAt: $now,
            ipHash: 'hash123',
            locale: 'pl',
            createdAt: $now,
            status: PriceAlert::STATUS_ACTIVE,
        );

        // Current price 2 600 zł -> above target -> no notification
        $this->assertFalse($alert->shouldNotifyForPrice(260000));

        // Current price 2 450 zł -> below target -> notify!
        $this->assertTrue($alert->shouldNotifyForPrice(245000));

        // If already notified for 2 450 zł, price stays 2 450 zł -> do not notify again
        $alertAlreadyNotified = new PriceAlert(
            id: 1,
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: 250000,
            verificationToken: 'token123',
            unsubscribeToken: 'unsub123',
            isVerified: true,
            verifiedAt: $now,
            ipHash: 'hash123',
            locale: 'pl',
            createdAt: $now,
            lastNotifiedAt: $now,
            lastNotifiedPriceGrosz: 245000,
            status: PriceAlert::STATUS_ACTIVE,
        );

        $this->assertFalse($alertAlreadyNotified->shouldNotifyForPrice(245000));

        // Price drops further to 2 300 zł -> notify!
        $this->assertTrue($alertAlreadyNotified->shouldNotifyForPrice(230000));
    }

    public function testShouldNotifyForAnyPriceDrop(): void
    {
        $now = new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC'));

        $alert = new PriceAlert(
            id: 1,
            productSlug: 'macbook-air-m1-256gb',
            email: 'user@example.com',
            targetPriceGrosz: null, // Any price drop
            verificationToken: 'token123',
            unsubscribeToken: 'unsub123',
            isVerified: true,
            verifiedAt: $now,
            ipHash: 'hash123',
            locale: 'pl',
            createdAt: $now,
            status: PriceAlert::STATUS_ACTIVE,
        );

        // Previous was 2 800 zł, current is 2 700 zł -> price dropped -> notify!
        $this->assertTrue($alert->shouldNotifyForPrice(270000, 280000));

        // Previous was 2 700 zł, current is 2 800 zł -> price rose -> no notification
        $this->assertFalse($alert->shouldNotifyForPrice(280000, 270000));

        // Previous was 2 700 zł, current is 2 700 zł -> flat -> no notification
        $this->assertFalse($alert->shouldNotifyForPrice(270000, 270000));
    }
}
