<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CheckPriceAlertsCommand;
use App\Market\Application\CheckPriceAlerts;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Tests\Market\MockPriceAlertMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckPriceAlertsCommandTest extends TestCase
{
    public function testExecuteRunsAlertCheck(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('history')->willReturnCallback(static function (string $slug): array {
            if ($slug === 'iphone-14-128gb') {
                return [
                    new PriceObservation(
                        'iphone-14-128gb',
                        new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC')),
                        230000,
                        220000,
                        250000,
                        5,
                        'high',
                        '',
                        PriceObservation::METHODOLOGY_MANUAL
                    ),
                ];
            }
            return [];
        });

        $alerts = $this->createStub(PriceAlertRepository::class);
        $alerts->method('findActiveForProduct')->willReturnCallback(static function (string $slug): array {
            if ($slug === 'iphone-14-128gb') {
                return [
                    new PriceAlert(
                        1,
                        'iphone-14-128gb',
                        'user@example.com',
                        240000,
                        'tok1',
                        'unsub1',
                        true,
                        new \DateTimeImmutable(),
                        'hash',
                        'pl',
                        new \DateTimeImmutable(),
                        status: PriceAlert::STATUS_ACTIVE
                    ),
                ];
            }
            return [];
        });

        $mailer = new MockPriceAlertMailer();
        $checkPriceAlerts = new CheckPriceAlerts($catalog, $observations, $alerts, $mailer);

        $command = new CheckPriceAlertsCommand($checkPriceAlerts);
        $tester = new CommandTester($command);

        $tester->execute(['--product' => 'iphone-14-128gb']);

        $tester->assertCommandIsSuccessful();
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('1 products checked', $output);
        self::assertStringContainsString('1 active subscribers evaluated', $output);
        self::assertStringContainsString('1 notifications dispatched', $output);
        self::assertStringContainsString('iphone-14-128gb', $output);
    }

    public function testExecuteDryRunMode(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('history')->willReturnCallback(static function (string $slug): array {
            if ($slug === 'iphone-14-128gb') {
                return [
                    new PriceObservation(
                        'iphone-14-128gb',
                        new \DateTimeImmutable('2026-08-25 10:00:00', new \DateTimeZone('UTC')),
                        230000,
                        220000,
                        250000,
                        5,
                        'high',
                        '',
                        PriceObservation::METHODOLOGY_MANUAL
                    ),
                ];
            }
            return [];
        });

        $alerts = $this->createStub(PriceAlertRepository::class);
        $alerts->method('findActiveForProduct')->willReturnCallback(static function (string $slug): array {
            if ($slug === 'iphone-14-128gb') {
                return [
                    new PriceAlert(
                        1,
                        'iphone-14-128gb',
                        'user@example.com',
                        240000,
                        'tok1',
                        'unsub1',
                        true,
                        new \DateTimeImmutable(),
                        'hash',
                        'pl',
                        new \DateTimeImmutable(),
                        status: PriceAlert::STATUS_ACTIVE
                    ),
                ];
            }
            return [];
        });

        $mailer = new MockPriceAlertMailer();
        $checkPriceAlerts = new CheckPriceAlerts($catalog, $observations, $alerts, $mailer);

        $command = new CheckPriceAlertsCommand($checkPriceAlerts);
        $tester = new CommandTester($command);

        $tester->execute(['--product' => 'iphone-14-128gb', '--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Running in DRY-RUN mode', $output);
        self::assertStringContainsString('1 notifications simulated', $output);
    }
}
