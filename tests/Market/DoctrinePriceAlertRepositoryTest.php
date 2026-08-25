<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceAlert;
use App\Market\Infrastructure\DoctrinePriceAlertRepository;
use App\Tests\DoctrineTestCase;

final class DoctrinePriceAlertRepositoryTest extends DoctrineTestCase
{
    private DoctrinePriceAlertRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DoctrinePriceAlertRepository($this->entityManager, 'test-secret');
    }

    public function testSubscribeCreatesPendingAlertAndFindsByTokens(): void
    {
        $alert = $this->repository->subscribe(
            productSlug: 'iphone-14-128gb',
            email: 'Test.User@Example.com',
            targetPriceGrosz: 240000,
            ipAddress: '127.0.0.1',
            locale: 'pl',
        );

        $this->assertSame('iphone-14-128gb', $alert->productSlug);
        $this->assertSame('test.user@example.com', $alert->email);
        $this->assertSame(240000, $alert->targetPriceGrosz);
        $this->assertFalse($alert->isVerified);
        $this->assertSame(PriceAlert::STATUS_PENDING, $alert->status);

        // Find by verification token
        $found = $this->repository->findByVerificationToken($alert->verificationToken);
        $this->assertNotNull($found);
        $this->assertSame($alert->email, $found->email);

        // Find by unsubscribe token
        $foundUnsub = $this->repository->findByUnsubscribeToken($alert->unsubscribeToken);
        $this->assertNotNull($foundUnsub);
        $this->assertSame($alert->email, $foundUnsub->email);
    }

    public function testMarkVerifiedActivatesAlert(): void
    {
        $alert = $this->repository->subscribe(
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: null,
            ipAddress: '192.168.1.1',
            locale: 'pl',
        );

        $verified = $this->repository->markVerified($alert->verificationToken);
        $this->assertNotNull($verified);
        $this->assertTrue($verified->isVerified);
        $this->assertSame(PriceAlert::STATUS_ACTIVE, $verified->status);
        $this->assertNotNull($verified->verifiedAt);

        $activeList = $this->repository->findActiveForProduct('iphone-14-128gb');
        $this->assertCount(1, $activeList);
        $this->assertSame('user@example.com', $activeList[0]->email);
    }

    public function testMarkUnsubscribedDeactivatesAlert(): void
    {
        $alert = $this->repository->subscribe(
            productSlug: 'iphone-14-128gb',
            email: 'user@example.com',
            targetPriceGrosz: null,
            ipAddress: '192.168.1.1',
            locale: 'pl',
        );

        $this->repository->markVerified($alert->verificationToken);
        $unsubscribed = $this->repository->markUnsubscribed($alert->unsubscribeToken);

        $this->assertNotNull($unsubscribed);
        $this->assertSame(PriceAlert::STATUS_UNSUBSCRIBED, $unsubscribed->status);

        $activeList = $this->repository->findActiveForProduct('iphone-14-128gb');
        $this->assertEmpty($activeList);
    }

    public function testUpdateNotificationStateAndCountStats(): void
    {
        $alert = $this->repository->subscribe(
            productSlug: 'macbook-air-m1-256gb',
            email: 'buyer@example.com',
            targetPriceGrosz: 260000,
            ipAddress: '127.0.0.1',
        );

        $this->repository->markVerified($alert->verificationToken);

        $stats = $this->repository->countStatistics();
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['active']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['unsubscribed']);

        $notifiedAt = new \DateTimeImmutable('2026-08-25 12:00:00', new \DateTimeZone('UTC'));
        $this->repository->updateNotificationState((int) $alert->id, $notifiedAt, 255000);

        $updated = $this->repository->findByUnsubscribeToken($alert->unsubscribeToken);
        $this->assertNotNull($updated);
        $this->assertSame(255000, $updated->lastNotifiedPriceGrosz);
    }
}
