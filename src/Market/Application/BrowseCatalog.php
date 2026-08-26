<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;

final class BrowseCatalog
{
    public const ITEMS_PER_PAGE = 100;

    /**
     * @param list<array{
     *     product: Product,
     *     family: ?ProductFamily,
     *     latest: ?PriceObservation,
     *     one_month_ago: ?PriceObservation,
     *     delta_grosz: ?int,
     *     delta_pct: ?float
     * }> $rows
     * @param array<string, mixed> $query
     * @return array{
     *     rows: list<array{
     *         product: Product,
     *         family: ?ProductFamily,
     *         latest: ?PriceObservation,
     *         one_month_ago: ?PriceObservation,
     *         delta_grosz: ?int,
     *         delta_pct: ?float
     *     }>,
     *     filters: array{brand: string, price: string, ram: string, chip: string, condition: string},
     *     options: array{brands: list<string>, prices: list<string>, ram: list<string>, chips: list<string>, conditions: list<string>},
     *     page: int,
     *     pages: int,
     *     total: int,
     *     has_filters: bool
     * }
     */
    public function execute(array $rows, array $query): array
    {
        $filters = [
            'brand' => $this->queryString($query, 'brand'),
            'price' => $this->queryString($query, 'price'),
            'ram' => $this->queryString($query, 'ram'),
            'chip' => $this->queryString($query, 'chip'),
            'condition' => $this->queryString($query, 'condition'),
        ];
        $options = $this->options($rows);

        $filtered = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matches($row, $filters)
        ));
        $total = count($filtered);
        $pages = max(1, (int) ceil($total / self::ITEMS_PER_PAGE));
        $requestedPage = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $page = min($requestedPage === false ? 1 : $requestedPage, $pages);

        return [
            'rows' => array_slice($filtered, ($page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE),
            'filters' => $filters,
            'options' => $options,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'has_filters' => implode('', $filters) !== '',
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function queryString(array $query, string $key): string
    {
        $value = $query[$key] ?? '';

        return is_string($value) && mb_strlen($value) <= 80 ? trim($value) : '';
    }

    /**
     * @param list<array{product: Product, latest: ?PriceObservation}> $rows
     * @return array{brands: list<string>, prices: list<string>, ram: list<string>, chips: list<string>, conditions: list<string>}
     */
    private function options(array $rows): array
    {
        $brands = [];
        $ram = [];
        $chips = [];
        foreach ($rows as $row) {
            $product = $row['product'];
            $brands[$this->brand($product)] = true;
            $memory = $this->memory($product);
            if ($memory !== '') {
                $ram[$memory] = true;
            }
            $chip = $product->specifications['chip'] ?? '';
            if (is_string($chip) && $chip !== '') {
                $chips[$chip] = true;
            }
        }

        $brandValues = array_keys($brands);
        $ramValues = array_keys($ram);
        $chipValues = array_keys($chips);
        natcasesort($brandValues);
        natcasesort($ramValues);
        natcasesort($chipValues);

        return [
            'brands' => array_values($brandValues),
            'prices' => ['under-100', '100-2000', '2000-20000', 'over-20000'],
            'ram' => array_values($ramValues),
            'chips' => array_values($chipValues),
            'conditions' => ['used-functional'],
        ];
    }

    /**
     * @param array{product: Product, latest: ?PriceObservation} $row
     * @param array{brand: string, price: string, ram: string, chip: string, condition: string} $filters
     */
    private function matches(array $row, array $filters): bool
    {
        $product = $row['product'];
        if ($filters['brand'] !== '' && $filters['brand'] !== $this->brand($product)) {
            return false;
        }
        if ($filters['ram'] !== '' && $filters['ram'] !== $this->memory($product)) {
            return false;
        }
        if ($filters['chip'] !== '' && $filters['chip'] !== ($product->specifications['chip'] ?? '')) {
            return false;
        }
        if ($filters['condition'] !== '' && $filters['condition'] !== 'used-functional') {
            return false;
        }

        return $filters['price'] === '' || $this->matchesPrice($row['latest'], $filters['price']);
    }

    private function brand(Product $product): string
    {
        return match ($product->category) {
            'laptops', 'smartphones' => 'Apple',
            'ram' => 'Generic',
            default => strtok($product->name, ' ') ?: $product->name,
        };
    }

    private function memory(Product $product): string
    {
        $value = $product->specifications['memory'] ?? $product->specifications['capacity'] ?? '';

        return is_string($value) ? $value : '';
    }

    private function matchesPrice(?PriceObservation $latest, string $price): bool
    {
        if ($latest === null) {
            return false;
        }
        $pln = $latest->medianGrosz / 100;

        return match ($price) {
            'under-100' => $pln < 100,
            '100-2000' => $pln >= 100 && $pln < 2000,
            '2000-20000' => $pln >= 2000 && $pln <= 20000,
            'over-20000' => $pln > 20000,
            default => false,
        };
    }
}
