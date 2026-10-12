<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\BrowseCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrowseCatalogTest extends TestCase
{
    public function testFiltersConfigurationsAndPreservesFacetOptions(): void
    {
        $browser = new BrowseCatalog();
        $rows = [
            $this->row('macbook-m2', 'MacBook Air', 'laptops', ['memory' => '16 GB', 'chip' => 'M2'], 350000),
            $this->row('macbook-m3', 'MacBook Pro', 'laptops', ['memory' => '24 GB', 'chip' => 'M3'], 800000),
        ];

        $result = $browser->execute($rows, ['ram' => '16 GB', 'chip' => 'M2', 'price' => '2000-20000']);

        self::assertSame(1, $result['total']);
        self::assertSame('macbook-m2', $result['rows'][0]['product']->slug);
        self::assertSame(['16 GB', '24 GB'], $result['options']['ram']);
        self::assertTrue($result['has_filters']);
    }

    public function testPaginatesAtOneHundredConfigurations(): void
    {
        $rows = [];
        for ($index = 1; $index <= 205; ++$index) {
            $rows[] = $this->row('product-' . $index, 'Apple Product', 'smartphones', [], 150000);
        }

        $result = (new BrowseCatalog())->execute($rows, ['page' => '3']);

        self::assertSame(3, $result['pages']);
        self::assertSame(3, $result['page']);
        self::assertCount(5, $result['rows']);
    }

    public function testPhoneFiltersMatchExactModelAndStorageRatherThanRam(): void
    {
        $rows = [
            $this->phoneRow('iphone-15-128', 'iPhone 15', 'Standard', '128 GB', 170000),
            $this->phoneRow('iphone-15-256', 'iPhone 15', 'Standard', '256 GB', 190000),
            $this->phoneRow('iphone-15-pro-256', 'iPhone 15', 'Pro', '256 GB', 240000),
            $this->phoneRow('iphone-16-256', 'iPhone 16', 'Standard', '256 GB', 260000),
        ];
        $result = (new BrowseCatalog())->execute($rows, [
            'model' => 'iPhone 15 Pro',
            'storage' => '256 GB',
            'price' => '2000-3000',
        ], 'smartphones');

        self::assertSame(['iPhone 15', 'iPhone 15 Pro', 'iPhone 16'], $result['options']['models']);
        self::assertSame(['128 GB', '256 GB'], $result['options']['storage']);
        self::assertSame([], $result['options']['ram']);
        self::assertSame([], $result['options']['chips']);
        self::assertSame(1, $result['total']);
        self::assertSame('iphone-15-pro-256', $result['rows'][0]['product']->slug);
        self::assertSame('iPhone 15 Pro', $result['filters']['model']);
        self::assertSame('256 GB', $result['filters']['storage']);
    }

    public function testStorageOptionsSortByCapacityAcrossGbAndTb(): void
    {
        $rows = [];
        foreach (['1 TB', '256 GB', '2 TB', '64 GB', '512 GB'] as $index => $capacity) {
            $rows[] = $this->phoneRow('iphone-' . $index, 'iPhone 15', 'Pro', $capacity, 200000);
        }
        $result = (new BrowseCatalog())->execute($rows, []);

        self::assertSame(['64 GB', '256 GB', '512 GB', '1 TB', '2 TB'], $result['options']['storage']);
    }

    public function testLegacyPhonePriceBandRemainsSelectable(): void
    {
        $rows = [$this->phoneRow('iphone-15-256', 'iPhone 15', 'Standard', '256 GB', 190000)];
        $result = (new BrowseCatalog())->execute($rows, ['price' => '100-2000']);

        self::assertSame(1, $result['total']);
        self::assertSame('100-2000', $result['filters']['price']);
        self::assertContains('100-2000', $result['options']['prices']);
    }

    public function testUnknownAndArrayFiltersCannotHideTheCatalog(): void
    {
        $rows = [$this->phoneRow('iphone-15-256', 'iPhone 15', 'Standard', '256 GB', 190000)];
        $result = (new BrowseCatalog())->execute($rows, [
            'model' => ['iPhone 15'],
            'storage' => '999 GB',
            'price' => 'bogus',
            'ram' => '256 GB',
            'chip' => 'M3',
            'brand' => str_repeat('A', 81),
            'page' => ['999'],
        ]);

        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertFalse($result['has_filters']);
    }

    public function testRamCategoryKeepsCapacityFacetAndGeneralPriceBands(): void
    {
        $rows = [
            $this->row('ddr4-8', 'DDR4', 'ram', ['capacity' => '8 GB'], 6500),
            $this->row('ddr5-16', 'DDR5', 'ram', ['capacity' => '16 GB'], 15000),
        ];
        $result = (new BrowseCatalog())->execute($rows, ['ram' => '16 GB', 'price' => '100-2000'], 'ram');

        self::assertSame(['8 GB', '16 GB'], $result['options']['ram']);
        self::assertSame([], $result['options']['models']);
        self::assertSame([], $result['options']['storage']);
        self::assertSame(['under-100', '100-2000', '2000-20000', 'over-20000'], $result['options']['prices']);
        self::assertSame(1, $result['total']);
        self::assertSame('ddr5-16', $result['rows'][0]['product']->slug);
    }

    /** @return iterable<string, array{int, string}> */
    public static function phonePriceBoundaries(): iterable
    {
        yield 'below 500' => [49999, 'under-500'];
        yield 'at 500' => [50000, '500-1000'];
        yield 'below 1000' => [99999, '500-1000'];
        yield 'at 1000' => [100000, '1000-2000'];
        yield 'below 2000' => [199999, '1000-2000'];
        yield 'at 2000' => [200000, '2000-3000'];
        yield 'below 3000' => [299999, '2000-3000'];
        yield 'at 3000' => [300000, '3000-5000'];
        yield 'at 5000' => [500000, '3000-5000'];
        yield 'above 5000' => [500001, 'over-5000'];
    }

    #[DataProvider('phonePriceBoundaries')]
    public function testPhonePriceBandsHaveNoBoundaryGapsOrOverlaps(int $amount, string $matchingBand): void
    {
        $browser = new BrowseCatalog();
        $rows = [$this->phoneRow('iphone-15-256', 'iPhone 15', 'Standard', '256 GB', $amount)];
        foreach ($browser->execute($rows, [])['options']['prices'] as $band) {
            self::assertSame($band === $matchingBand ? 1 : 0, $browser->execute($rows, ['price' => $band])['total']);
        }
    }

    /** @return array{product: Product, family: null, latest: PriceObservation, one_month_ago: null, delta_grosz: null, delta_pct: null} */
    private function phoneRow(
        string $slug,
        string $generation,
        string $variant,
        string $storage,
        int $price,
    ): array {
        return $this->row($slug, $generation, 'smartphones', [
            'generation' => $generation,
            'variant' => $variant,
            'storage' => $storage,
            'memory' => '8 GB',
            'chip' => 'A17',
        ], $price);
    }

    /**
     * @param array<string, mixed> $specifications
     * @return array{
     *     product: Product,
     *     family: null,
     *     latest: PriceObservation,
     *     one_month_ago: null,
     *     delta_grosz: null,
     *     delta_pct: null
     * }
     */
    private function row(
        string $slug,
        string $name,
        string $category,
        array $specifications,
        int $medianGrosz,
    ): array {
        return [
            'product' => new Product($slug, $name, 'Definition', $category, $specifications),
            'family' => null,
            'latest' => new PriceObservation(
                $slug,
                new \DateTimeImmutable('2026-08-26'),
                $medianGrosz,
                $medianGrosz - 1000,
                $medianGrosz + 1000,
                'medium',
                '',
                PriceObservation::METHODOLOGY_MANUAL,
            ),
            'one_month_ago' => null,
            'delta_grosz' => null,
            'delta_pct' => null,
        ];
    }
}
