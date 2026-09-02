<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;

final class GetProductPriceHistory
{
    /** @var array<string, list<PriceObservation>> */
    private array $historyCache = [];

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PriceObservationRepository $observations,
    ) {
    }

    /**
     * @return list<PriceObservation>
     */
    public function forProduct(string $slug): array
    {
        if (!array_key_exists($slug, $this->historyCache)) {
            $this->historyCache[$slug] = $this->observations->history($slug);
        }

        return $this->historyCache[$slug];
    }

    public function latestForProduct(string $slug): ?PriceObservation
    {
        if (array_key_exists($slug, $this->historyCache)) {
            return $this->historyCache[$slug][0] ?? null;
        }

        return $this->observations->latest($slug);
    }

    /** @param list<string> $productSlugs */
    public function preload(array $productSlugs): void
    {
        $missing = array_values(array_filter(
            array_unique($productSlugs),
            fn (string $slug): bool => !array_key_exists($slug, $this->historyCache),
        ));
        if ($missing === []) {
            return;
        }

        $histories = $this->observations->histories($missing);
        foreach ($missing as $slug) {
            $this->historyCache[$slug] = $histories[$slug] ?? [];
        }
    }

    /**
     * @return array{
     *     product: Product,
     *     family: ?ProductFamily,
     *     history: list<PriceObservation>,
     *     latest: ?PriceObservation,
     *     one_month_ago: ?PriceObservation
     * }|null
     */
    public function detailedHistory(string $slug): ?array
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            return null;
        }

        $history = $this->forProduct($slug);
        $family = $this->catalog->familyFor($slug);
        $latest = $history[0] ?? null;

        $oneMonthAgo = null;
        if ($latest !== null) {
            $targetTimestamp = $latest->observedAt->getTimestamp() - (30 * 86400);
            $closestDiff = null;
            foreach (array_slice($history, 1) as $item) {
                $diff = abs($item->observedAt->getTimestamp() - $targetTimestamp);
                if ($diff <= 15 * 86400) {
                    if ($closestDiff === null || $diff < $closestDiff) {
                        $closestDiff = $diff;
                        $oneMonthAgo = $item;
                    }
                }
            }
        }

        return [
            'product' => $product,
            'family' => $family,
            'history' => $history,
            'latest' => $latest,
            'one_month_ago' => $oneMonthAgo,
        ];
    }

    /**
     * @return list<array{
     *     product: Product,
     *     family: ?ProductFamily,
     *     latest: PriceObservation,
     *     one_month_ago: PriceObservation,
     *     delta_grosz: int,
     *     delta_pln: float,
     *     delta_pct: float
     * }>
     */
    public function marketMovers(?string $category = null, ?string $familyFilter = null, int $limit = 6, bool $dropsOnly = true): array
    {
        $products = $category !== null
            ? $this->catalog->productsForHub($category, $familyFilter)
            : $this->catalog->all();

        $movers = [];
        foreach ($products as $product) {
            $detailed = $this->detailedHistory($product->slug);
            if (
                $detailed === null || $detailed['latest'] === null || $detailed['one_month_ago'] === null
                || $detailed['latest']->availability !== 'available'
                || $detailed['one_month_ago']->availability !== 'available'
            ) {
                continue;
            }

            $latest = $detailed['latest'];
            $past = $detailed['one_month_ago'];
            $deltaGrosz = $latest->medianGrosz - $past->medianGrosz;

            if ($dropsOnly && $deltaGrosz >= 0) {
                continue;
            }

            $deltaPln = $deltaGrosz / 100;
            $deltaPct = $past->medianGrosz > 0
                ? round(($deltaGrosz / $past->medianGrosz) * 100, 1)
                : 0.0;

            $movers[] = [
                'product' => $product,
                'family' => $detailed['family'],
                'latest' => $latest,
                'one_month_ago' => $past,
                'delta_grosz' => $deltaGrosz,
                'delta_pln' => $deltaPln,
                'delta_pct' => $deltaPct,
            ];
        }

        usort($movers, static fn (array $a, array $b): int => $a['delta_pct'] <=> $b['delta_pct']);

        if ($limit > 0 && count($movers) > $limit) {
            return array_slice($movers, 0, $limit);
        }

        return $movers;
    }

    /**
     * @return array{
     *     total_products: int,
     *     total_families: int,
     *     min_price_grosz: ?int,
     *     max_price_grosz: ?int,
     *     median_price_grosz: ?int,
     *     has_observations: bool
     * }
     */
    public function categoryStatistics(string $category, ?string $familyFilter = null): array
    {
        $products = $this->catalog->productsForHub($category, $familyFilter);
        $families = $this->catalog->familiesForHub($category, $familyFilter);

        $prices = [];
        foreach ($products as $product) {
            $latest = $this->latestForProduct($product->slug);
            if ($latest !== null && $latest->availability === 'available' && $latest->medianGrosz > 0) {
                $prices[] = $latest->medianGrosz;
            }
        }

        if ($prices === []) {
            return [
                'total_products' => count($products),
                'total_families' => count($families),
                'min_price_grosz' => null,
                'max_price_grosz' => null,
                'median_price_grosz' => null,
                'has_observations' => false,
            ];
        }

        sort($prices);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $median = $prices[(int) floor(count($prices) / 2)];

        return [
            'total_products' => count($products),
            'total_families' => count($families),
            'min_price_grosz' => $min,
            'max_price_grosz' => $max,
            'median_price_grosz' => $median,
            'has_observations' => true,
        ];
    }

    /**
     * @return list<array{
     *     product: Product,
     *     family: ?ProductFamily,
     *     latest: ?PriceObservation,
     *     one_month_ago: ?PriceObservation,
     *     delta_grosz: ?int,
     *     delta_pct: ?float
     * }>
     */
    public function categoryMatrix(string $category, ?string $familyFilter = null): array
    {
        $products = $this->catalog->productsForHub($category, $familyFilter);
        $rows = [];

        foreach ($products as $product) {
            $detailed = $this->detailedHistory($product->slug);
            $latest = $detailed['latest'] ?? null;
            $oneMonthAgo = $detailed['one_month_ago'] ?? null;
            $deltaGrosz = ($latest !== null && $oneMonthAgo !== null)
                ? ($latest->medianGrosz - $oneMonthAgo->medianGrosz)
                : null;
            $deltaPct = ($deltaGrosz !== null && $oneMonthAgo->medianGrosz > 0)
                ? round(($deltaGrosz / $oneMonthAgo->medianGrosz) * 100, 1)
                : null;

            $rows[] = [
                'product' => $product,
                'family' => $detailed['family'] ?? $this->catalog->familyFor($product->slug),
                'latest' => $latest,
                'one_month_ago' => $oneMonthAgo,
                'delta_grosz' => $deltaGrosz,
                'delta_pct' => $deltaPct,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $priceA = $a['latest']?->availability === 'available' ? $a['latest']->medianGrosz : -1;
            $priceB = $b['latest']?->availability === 'available' ? $b['latest']->medianGrosz : -1;
            if ($priceA !== $priceB) {
                return $priceB <=> $priceA;
            }

            return strcmp($a['product']->name, $b['product']->name);
        });

        return $rows;
    }
}
