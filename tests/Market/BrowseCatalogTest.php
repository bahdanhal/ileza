<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\BrowseCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
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
