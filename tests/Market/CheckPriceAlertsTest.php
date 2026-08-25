<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\CheckPriceAlerts;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Infrastructure\DoctrinePriceAlertRepository;
use App\Market\Infrastructure\DoctrinePriceObservationRepository;
use App\Tests\DoctrineTestCase;

final class CheckPriceAlertsTest extends DoctrineTestCase
{
    private ProductCatalog $catalog;
    private PriceObservationRepository $observationRepository;
    private PriceAlertRepository $alertRepository;
    private MockPriceAlertMailer $mailer;
    private CheckPriceAlerts $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new ProductCatalog();
        $this->observationRepository = new DoctrinePriceObservationRepository($this->entityManager);
        $this->alertRepository = new DoctrinePriceAlertRepository($this->entityManager, 'secret-check');
        $this->mailer = new MockPriceAlertMailer();
        $this->checker = new CheckPriceAlerts(
            $this->catalog,
            $this->observationRepository,
            $this->alertRepository,
            $this->mailer
        );
    }

    public function testCheckDispatchesNotificationWhenPriceDropsBelowTarget(): void
    {
        // 1. Subscribe alert for iPhone 14 with target 2 400 zł
        $alert = $this->alertRepository->subscribe(
            productSlug: 'iphone-14-128gb',
            email: 'buyer@example.com',
            targetPriceGrosz: 240000,
            ipAddress: '127.0.0.1',
        );
        $this->alertRepository->markVerified($alert->verificationToken);

        // 2. Add an observation at 2 350 zł (below 2 400 zł threshold)
        $obs = new PriceObservation(
            'iphone-14-128gb',
            new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC')),
            235000,
            220000,
            250000,
            5,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $this->observationRepository->save($obs);

        // 3. Execute check
        $report = $this->checker->execute('iphone-14-128gb');

        $this->assertSame(1, $report['notifications_sent']);
        $this->assertCount(1, $this->mailer->dropNotifications);
        $this->assertSame('buyer@example.com', $this->mailer->dropNotifications[0]['alert']->email);
        $this->assertSame(235000, $this->mailer->dropNotifications[0]['latest']->medianGrosz);

        // 4. Running check again without price change shouldn't re-dispatch
        $secondReport = $this->checker->execute('iphone-14-128gb');
        $this->assertSame(0, $secondReport['notifications_sent']);
    }

    public function testDryRunSimulatesWithoutSending(): void
    {
        $alert = $this->alertRepository->subscribe(
            productSlug: 'iphone-14-128gb',
            email: 'sim@example.com',
            targetPriceGrosz: 250000,
            ipAddress: '127.0.0.1',
        );
        $this->alertRepository->markVerified($alert->verificationToken);

        $obs = new PriceObservation(
            'iphone-14-128gb',
            new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC')),
            240000,
            220000,
            260000,
            5,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $this->observationRepository->save($obs);

        $report = $this->checker->execute('iphone-14-128gb', dryRun: true);
        $this->assertSame(1, $report['notifications_sent']);
        $this->assertCount(0, $this->mailer->dropNotifications);
    }
}
