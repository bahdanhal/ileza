<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\GetNextPriceResearchBatch;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;
use PHPUnit\Framework\TestCase;

final class GetNextPriceResearchBatchTest extends TestCase
{
    public function testDefaultsToTwentyProducts(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('histories')->willReturn([]);

        $service = new GetNextPriceResearchBatch($catalog, $repository);
        $result = $service->execute();

        self::assertSame(20, $result['batch_size']);
        self::assertCount(20, $result['products']);
        self::assertGreaterThan(20, $result['eligible_count']);
        self::assertTrue($result['has_more']);
        self::assertSame('missing_observation', $result['products'][0]['reason']);
    }

    public function testRespectsCustomLimit(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('histories')->willReturn([]);

        $service = new GetNextPriceResearchBatch($catalog, $repository);
        $result = $service->execute([], limit: 5);

        self::assertSame(5, $result['batch_size']);
        self::assertCount(5, $result['products']);
        self::assertTrue($result['has_more']);
    }

    public function testClampsLimitToBetweenOneAndFifty(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('histories')->willReturn([]);

        $service = new GetNextPriceResearchBatch($catalog, $repository);

        $lowResult = $service->execute([], limit: 0);
        self::assertSame(1, $lowResult['batch_size']);
        self::assertCount(1, $lowResult['products']);

        $highResult = $service->execute([], limit: 100);
        self::assertSame(50, $highResult['batch_size']);
        self::assertCount(50, $highResult['products']);
    }

    public function testExcludesSlugsFromQueue(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('histories')->willReturn([]);

        $service = new GetNextPriceResearchBatch($catalog, $repository);
        $all = $service->execute([], limit: 5);
        $firstSlug = $all['products'][0]['slug'];

        $filtered = $service->execute([$firstSlug], limit: 5);
        $filteredSlugs = array_column($filtered['products'], 'slug');

        self::assertNotContains($firstSlug, $filteredSlugs);
        self::assertSame($all['eligible_count'] - 1, $filtered['eligible_count']);
    }

    public function testPrioritizesMissingObservationsThenOldest(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $allSlugs = array_map(static fn (Product $p): string => $p->slug, $catalog->all());
        $slugA = $allSlugs[0];
        $slugB = $allSlugs[1];
        $missingSlug = $allSlugs[2];

        // Provide history for slugA (old observation) and slugB (even older observation)
        $histories = [
            $slugA => [
                new PriceObservation(
                    $slugA,
                    new \DateTimeImmutable('2026-08-01 12:00:00'),
                    200000,
                    180000,
                    220000,
                    'available',
                    'A note',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
            ],
            $slugB => [
                new PriceObservation(
                    $slugB,
                    new \DateTimeImmutable('2026-07-01 12:00:00'),
                    300000,
                    270000,
                    330000,
                    'available',
                    'B note',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
            ],
        ];

        $repository->method('histories')->willReturn($histories);

        $service = new GetNextPriceResearchBatch($catalog, $repository);
        $excluded = array_diff($allSlugs, [$slugA, $slugB, $missingSlug]);
        $result = $service->execute(array_values($excluded), limit: 10);

        self::assertSame(3, $result['eligible_count']);
        self::assertCount(3, $result['products']);

        // Item 0: missing observation
        self::assertSame($missingSlug, $result['products'][0]['slug']);
        self::assertSame('missing_observation', $result['products'][0]['reason']);

        // Item 1: slugB (older: 2026-07-01)
        self::assertSame($slugB, $result['products'][1]['slug']);
        self::assertSame('oldest_observation', $result['products'][1]['reason']);

        // Item 2: slugA (newer: 2026-08-01)
        self::assertSame($slugA, $result['products'][2]['slug']);
        self::assertSame('oldest_observation', $result['products'][2]['reason']);
    }

    public function testExcludesObservationsFromTodayAndYesterday(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-09-28 12:00:00', new \DateTimeZone('Europe/Warsaw'));
        $allSlugs = array_map(static fn (Product $p): string => $p->slug, $catalog->all());
        $todaySlug = $allSlugs[0];
        $yesterdaySlug = $allSlugs[1];
        $oldSlug = $allSlugs[2];

        $histories = [
            $todaySlug => [
                new PriceObservation(
                    $todaySlug,
                    new \DateTimeImmutable('2026-09-28 10:00:00', new \DateTimeZone('Europe/Warsaw')),
                    100000,
                    90000,
                    110000,
                    'available',
                    'today note',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
            ],
            $yesterdaySlug => [
                new PriceObservation(
                    $yesterdaySlug,
                    new \DateTimeImmutable('2026-09-27 15:00:00', new \DateTimeZone('Europe/Warsaw')),
                    150000,
                    130000,
                    170000,
                    'available',
                    'yesterday note',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
            ],
            $oldSlug => [
                new PriceObservation(
                    $oldSlug,
                    new \DateTimeImmutable('2026-09-20 12:00:00', new \DateTimeZone('Europe/Warsaw')),
                    200000,
                    180000,
                    220000,
                    'available',
                    'old note',
                    PriceObservation::METHODOLOGY_MANUAL
                ),
            ],
        ];

        $repository->method('histories')->willReturn($histories);

        $service = new GetNextPriceResearchBatch($catalog, $repository);
        $excluded = array_diff($allSlugs, [$todaySlug, $yesterdaySlug, $oldSlug]);
        $result = $service->execute(array_values($excluded), limit: 10, now: $now);

        self::assertSame(1, $result['eligible_count']);
        self::assertCount(1, $result['products']);
        self::assertSame($oldSlug, $result['products'][0]['slug']);
    }
}
