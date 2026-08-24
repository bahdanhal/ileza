<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class GetProductPriceHistoryMoversTest extends TestCase
{
    public function testMarketMoversCalculatesAccurateDeltasAndSortsDrops(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $monthAgo = $now->modify('-30 days');

        // Product A: 2000 PLN now, was 2500 PLN (-500 PLN, -20.0%)
        $iphone13Latest = new PriceObservation(
            'iphone-13-128gb',
            $now,
            200000,
            180000,
            220000,
            8,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $iphone13Past = new PriceObservation(
            'iphone-13-128gb',
            $monthAgo,
            250000,
            230000,
            270000,
            8,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        // Product B: 3000 PLN now, was 3300 PLN (-300 PLN, -9.1%)
        $macbookM1Latest = new PriceObservation(
            'macbook-air-13-m1-8-gb-ram-256-gb-ssd',
            $now,
            300000,
            280000,
            320000,
            10,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $macbookM1Past = new PriceObservation(
            'macbook-air-13-m1-8-gb-ram-256-gb-ssd',
            $monthAgo,
            330000,
            310000,
            350000,
            10,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        $repository->method('history')->willReturnCallback(
            static function (string $slug) use ($iphone13Latest, $iphone13Past, $macbookM1Latest, $macbookM1Past): array {
                return match ($slug) {
                    'iphone-13-128gb' => [$iphone13Latest, $iphone13Past],
                    'macbook-air-13-m1-8-gb-ram-256-gb-ssd' => [$macbookM1Latest, $macbookM1Past],
                    default => [],
                };
            }
        );

        $service = new GetProductPriceHistory($catalog, $repository);
        $movers = $service->marketMovers(limit: 10, dropsOnly: true);

        self::assertCount(2, $movers);
        // Biggest drop first (-20.0% before -9.1%)
        self::assertSame('iphone-13-128gb', $movers[0]['product']->slug);
        self::assertSame(-50000, $movers[0]['delta_grosz']);
        self::assertEquals(-500.0, $movers[0]['delta_pln']);
        self::assertSame(-20.0, $movers[0]['delta_pct']);

        self::assertSame('macbook-air-13-m1-8-gb-ram-256-gb-ssd', $movers[1]['product']->slug);
        self::assertSame(-30000, $movers[1]['delta_grosz']);
        self::assertEquals(-300.0, $movers[1]['delta_pln']);
        self::assertSame(-9.1, $movers[1]['delta_pct']);
    }

    public function testCategoryStatisticsComputesPriceBands(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));

        $repository->method('latest')->willReturnCallback(static function (string $slug) use ($now): ?PriceObservation {
            return match ($slug) {
                'ram-ddr4-desktop-8-gb-3200-mhz' => new PriceObservation(
                    $slug,
                    $now,
                    8000,
                    7000,
                    9000,
                    5,
                    'high',
                    '',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
                'ram-ddr4-desktop-16-gb-3200-mhz' => new PriceObservation(
                    $slug,
                    $now,
                    14000,
                    12000,
                    16000,
                    5,
                    'high',
                    '',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
                'ram-ddr5-desktop-32-gb-6000-mhz' => new PriceObservation(
                    $slug,
                    $now,
                    45000,
                    40000,
                    50000,
                    5,
                    'high',
                    '',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
                default => null,
            };
        });

        $service = new GetProductPriceHistory($catalog, $repository);
        $stats = $service->categoryStatistics('ram');

        self::assertTrue($stats['has_observations']);
        self::assertSame(8000, $stats['min_price_grosz']);
        self::assertSame(45000, $stats['max_price_grosz']);
        self::assertSame(14000, $stats['median_price_grosz']);
    }

    public function testCategoryMatrixReturnsSortedRows(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $monthAgo = $now->modify('-30 days');

        $repository->method('history')->willReturnCallback(static function (string $slug) use ($now, $monthAgo): array {
            return match ($slug) {
                'peugeot-206-cc-1-6-petrol' => [
                    new PriceObservation($slug, $now, 800000, 700000, 900000, 6, 'high', '', PriceObservation::METHODOLOGY_MANUAL),
                    new PriceObservation($slug, $monthAgo, 850000, 750000, 950000, 6, 'high', '', PriceObservation::METHODOLOGY_MANUAL),
                ],
                'peugeot-206-cc-2-0-petrol' => [
                    new PriceObservation($slug, $now, 1100000, 950000, 1250000, 6, 'high', '', PriceObservation::METHODOLOGY_MANUAL),
                    new PriceObservation($slug, $monthAgo, 1100000, 950000, 1250000, 6, 'high', '', PriceObservation::METHODOLOGY_MANUAL),
                ],
                default => [],
            };
        });

        $service = new GetProductPriceHistory($catalog, $repository);
        $matrix = $service->categoryMatrix('cars');

        self::assertCount(2, $matrix);
        // Higher price first (2.0 petrol @ 11 000 zł, then 1.6 petrol @ 8 000 zł)
        self::assertSame('peugeot-206-cc-2-0-petrol', $matrix[0]['product']->slug);
        self::assertSame(0, $matrix[0]['delta_grosz']);

        self::assertSame('peugeot-206-cc-1-6-petrol', $matrix[1]['product']->slug);
        self::assertSame(-50000, $matrix[1]['delta_grosz']);
        self::assertSame(-5.9, $matrix[1]['delta_pct']);
    }
}
