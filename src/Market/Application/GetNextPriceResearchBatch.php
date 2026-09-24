<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;

final readonly class GetNextPriceResearchBatch
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
    ) {
    }

    /**
     * @param list<string> $excludedSlugs
     * @return array<string, mixed>
     */
    public function execute(array $excludedSlugs = [], ?\DateTimeImmutable $now = null): array
    {
        $timezone = new \DateTimeZone('Europe/Warsaw');
        $today = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone)->setTime(0, 0);
        $cutoff = $today->modify('-1 day')->format('Y-m-d');
        $products = $this->catalog->all();
        // Reload in bulk on every call so writes made earlier in the MCP session advance the queue.
        $histories = $this->observations->histories(array_map(static fn (Product $product): string => $product->slug, $products));
        $excluded = array_fill_keys($excludedSlugs, true);
        $queue = [];

        foreach ($products as $product) {
            if (isset($excluded[$product->slug])) {
                continue;
            }

            $latest = $histories[$product->slug][0] ?? null;
            $date = $latest?->observedAt->format('Y-m-d');
            if ($date !== null && $date >= $cutoff) {
                continue;
            }

            $queue[] = [
                'slug' => $product->slug,
                'name' => $product->name,
                'definition' => $product->definition,
                'category' => $product->category,
                'family_slug' => $product->familySlug,
                'configuration' => $product->specifications,
                'latest_observed_at' => $date,
                'latest_fair_price_pln' => $latest?->availability === 'available' ? $latest->medianGrosz / 100 : null,
                'availability' => $latest?->availability,
                'reason' => $latest === null ? 'missing_observation' : 'oldest_observation',
                'canonical_url' => 'https://ileza.pl/ceny/' . $product->slug,
            ];
        }

        usort($queue, static function (array $left, array $right): int {
            return strcmp($left['latest_observed_at'] ?? '', $right['latest_observed_at'] ?? '')
                ?: strcmp($left['slug'], $right['slug']);
        });

        return [
            'observed_at' => $today->format('Y-m-d'),
            'reviewed_before' => $cutoff,
            'batch_size' => 10,
            'eligible_count' => count($queue),
            'has_more' => count($queue) > 10,
            'products' => array_slice($queue, 0, 10),
            'queue_semantics' => 'Read-only, not a reservation. Save verified observations, then request the next batch. '
                . 'Unwritten products remain eligible; exclude explicitly deferred slugs when continuing other work.',
        ];
    }
}
