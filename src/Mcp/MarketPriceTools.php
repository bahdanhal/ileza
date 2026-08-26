<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Domain\ProductRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class MarketPriceTools
{
    public function __construct(
        private ProductCatalog $catalog,
        private GetProductPriceHistory $priceHistory,
        private RecordPriceObservation $recordObservation,
        private ?AdminAccess $adminAccess = null,
        private ?ProductRepository $productRepository = null,
    ) {
    }

    #[McpTool(
        name: 'list_polish_fair_price_products',
        description: 'List products tracked by IleZa.pl for manual editorial fair-price history in Poland.'
    )]
    public function listProducts(): string
    {
        return $this->json([
            'products' => array_map(fn ($product) => [
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category,
                'family_slug' => $product->familySlug,
                'family_name' => $product->familyName,
                'image' => $product->image,
                'configuration' => $product->specifications,
                'has_observations' => $this->priceHistory->latestForProduct($product->slug) !== null,
                'canonical_url' => $this->canonicalUrl($product->slug),
            ], $this->catalog->all()),
            'terms' => 'Public read-only estimates; no account or API key required.',
        ]);
    }

    #[McpTool(
        name: 'get_polish_fair_price_product',
        description: 'Get full product specification, family grouping, and media details for a product slug.'
    )]
    public function getProduct(#[Schema(description: 'Product slug returned by list_polish_fair_price_products.')] string $slug): string
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->json(['error' => 'Unknown product slug.', 'suggestion' => 'Call list_polish_fair_price_products first.']);
        }

        $latest = $this->priceHistory->latestForProduct($slug);

        return $this->json([
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category,
                'definition' => $product->definition,
                'family_slug' => $product->familySlug,
                'family_name' => $product->familyName,
                'image' => $product->image,
                'image_credit' => $product->imageCredit,
                'image_source' => $product->imageSource,
                'specifications' => $product->specifications,
                'latest_fair_price_pln' => $latest !== null ? $latest->medianGrosz / 100 : null,
                'latest_observed_at' => $latest?->observedAt->format('Y-m-d'),
            ],
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    #[McpTool(
        name: 'get_polish_fair_price_history',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Get dated manual editorial fair-price estimates for one exact product definition in Poland.'
    )]
    public function getHistory(#[Schema(description: 'Product slug returned by list_polish_fair_price_products.')] string $slug): string
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->json(['error' => 'Unknown product slug.', 'suggestion' => 'Call list_polish_fair_price_products first.']);
        }

        return $this->json([
            'product' => ['slug' => $product->slug, 'name' => $product->name, 'configuration' => $product->specifications],
            'market' => 'Poland',
            'currency' => 'PLN',
            'observations' => array_map(static fn ($item) => [
                'observed_at' => $item->observedAt->format('Y-m-d'),
                'fair_price_pln' => $item->medianGrosz / 100,
                'reasonable_low_pln' => $item->lowGrosz / 100,
                'reasonable_high_pln' => $item->highGrosz / 100,
                'confidence' => $item->confidence,
            ], $this->priceHistory->forProduct($slug)),
            // phpcs:ignore Generic.Files.LineLength
            'methodology' => 'Manual editorial estimate based on product knowledge and available market information. It is not a statistical sample, automated pricing algorithm, completed-sale statistic, or purchasing advice.',
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    #[McpTool(
        name: 'update_polish_fair_price_observation',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only tool: Add or update a manual editorial fair-price estimate for Poland. Authorization is handled via the Authorization header — do NOT pass any token argument.'
    )]
    public function updateObservation(
        #[Schema(description: 'Product slug to update (must exist in catalog).')] string $slug,
        #[Schema(description: 'Manual editorial fair-price estimate in PLN.')] float $fair_price_pln,
        #[Schema(description: 'Optional lower bound of the reasonable price range in PLN.')] ?float $low_pln = null,
        #[Schema(description: 'Optional upper bound of the reasonable price range in PLN.')] ?float $high_pln = null,
        #[Schema(description: 'Optional confidence level: low, medium, high (defaults to high).')] ?string $confidence = null,
        #[Schema(description: 'Optional observation date in YYYY-MM-DD or ISO 8601 format (defaults to current date).')] ?string $observed_at = null,
        #[Schema(description: 'Optional summary note or verification details.')] ?string $summary = null,
    ): string {
        if ($this->adminAccess?->isGranted() !== true) {
            return $this->json(['error' => 'Unauthorized: Invalid admin token.']);
        }

        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->json(['error' => 'Unknown product slug.', 'suggestion' => 'Call list_polish_fair_price_products first.']);
        }

        $medianGrosz = (int) round($fair_price_pln * 100);
        $lowGrosz = $low_pln !== null ? (int) round($low_pln * 100) : (int) round($medianGrosz * 0.88);
        $highGrosz = $high_pln !== null ? (int) round($high_pln * 100) : (int) round($medianGrosz * 1.14);

        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            return $this->json(['error' => 'Inconsistent prices. Ensure low <= median <= high and median > 0.']);
        }

        $confidenceLevel = $confidence ?? 'high';
        if (!in_array($confidenceLevel, ['low', 'medium', 'high'], true)) {
            return $this->json(['error' => 'Confidence must be one of: low, medium, high.']);
        }

        try {
            $observedDate = $observed_at !== null ? new \DateTimeImmutable($observed_at) : new \DateTimeImmutable('now');
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD or ISO 8601.']);
        }

        $note = $summary ?? 'Manual IleZa.pl editorial fair-price estimate.';

        try {
            $this->recordObservation->execute(
                $slug,
                $observedDate,
                $medianGrosz,
                $lowGrosz,
                $highGrosz,
                $confidenceLevel,
                $note,
                PriceObservation::METHODOLOGY_MANUAL
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Observation saved successfully.',
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
            ],
            'observation' => [
                'observed_at' => $observedDate->format('Y-m-d H:i:sP'),
                'fair_price_pln' => $medianGrosz / 100,
                'reasonable_low_pln' => $lowGrosz / 100,
                'reasonable_high_pln' => $highGrosz / 100,
                'confidence' => $confidenceLevel,
                'summary' => $note,
            ],
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    #[McpTool(
        name: 'create_polish_fair_price_product',
        description: 'Admin-only: Create a new tracked product configuration in the database. Requires Bearer authorization.'
    )]
    public function createProduct(
        #[Schema(description: 'URL-safe product slug (e.g. iphone-16-pro-256gb).')] string $slug,
        #[Schema(description: 'Human-readable product name (e.g. Apple iPhone 16 Pro 256 GB).')] string $name,
        #[Schema(description: 'Flexible product category slug, such as smartphones, cosmetics, or cars.')] string $category,
        #[Schema(description: 'Exact product identity and the scope of the editorial price estimate.')] string $definition,
        #[Schema(description: 'Optional family slug (e.g. iphone-16).')] ?string $family_slug = null,
        #[Schema(description: 'Optional family name (e.g. Apple iPhone 16).')] ?string $family_name = null,
        #[Schema(description: 'Optional image path or URL (e.g. /images/market/iphone-16.jpg).')] ?string $image = null,
        #[Schema(description: 'Optional image attribution author.')] ?string $image_credit = null,
        #[Schema(description: 'Optional image source URL.')] ?string $image_source = null,
        #[Schema(description: 'Optional specifications as a JSON string or key-value object.')] ?string $specifications_json = null,
    ): string {
        if ($this->adminAccess?->isGranted() !== true) {
            return $this->json(['error' => 'Unauthorized: Invalid admin token.']);
        }

        $cleanSlug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $cleanSlug)) {
            return $this->json(['error' => 'Product slug must be lowercase alphanumeric with hyphens.']);
        }

        /** @var array<string, mixed> $specifications */
        $specifications = [];
        if ($specifications_json !== null && trim($specifications_json) !== '') {
            try {
                $decoded = json_decode($specifications_json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $specifications = $decoded;
                }
            } catch (\JsonException) {
                return $this->json(['error' => 'Specifications must be valid JSON format.']);
            }
        }

        $product = new Product(
            $cleanSlug,
            trim($name),
            trim($definition),
            trim($category),
            $specifications,
            $family_slug !== null && trim($family_slug) !== '' ? trim($family_slug) : $cleanSlug,
            $family_name !== null && trim($family_name) !== '' ? trim($family_name) : trim($name),
            $image !== null && trim($image) !== '' ? trim($image) : null,
            $image_credit !== null && trim($image_credit) !== '' ? trim($image_credit) : null,
            $image_source !== null && trim($image_source) !== '' ? trim($image_source) : null,
        );

        if ($this->productRepository !== null) {
            try {
                $this->productRepository->save($product);
            } catch (\Throwable $e) {
                return $this->json(['error' => 'Failed to save product: ' . $e->getMessage()]);
            }
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Product created successfully.',
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category,
                'family_slug' => $product->familySlug,
                'family_name' => $product->familyName,
                'image' => $product->image,
            ],
            'canonical_url' => $this->canonicalUrl($cleanSlug),
        ]);
    }

    #[McpTool(
        name: 'update_polish_fair_price_product',
        description: 'Admin-only: Update an existing tracked product configuration. Requires Bearer authorization.'
    )]
    public function updateProduct(
        #[Schema(description: 'Existing product slug to update.')] string $slug,
        #[Schema(description: 'Updated human-readable product name.')] ?string $name = null,
        #[Schema(description: 'Updated category.')] ?string $category = null,
        #[Schema(description: 'Updated product identity and estimate scope.')] ?string $definition = null,
        #[Schema(description: 'Updated family slug.')] ?string $family_slug = null,
        #[Schema(description: 'Updated family name.')] ?string $family_name = null,
        #[Schema(description: 'Updated image path or URL.')] ?string $image = null,
        #[Schema(description: 'Updated image credit author.')] ?string $image_credit = null,
        #[Schema(description: 'Updated image source URL.')] ?string $image_source = null,
        #[Schema(description: 'Updated specifications JSON string.')] ?string $specifications_json = null,
    ): string {
        if ($this->adminAccess?->isGranted() !== true) {
            return $this->json(['error' => 'Unauthorized: Invalid admin token.']);
        }

        $existing = $this->productRepository?->get($slug) ?? $this->catalog->get($slug);
        if ($existing === null) {
            return $this->json(['error' => 'Unknown product slug: ' . $slug]);
        }

        /** @var array<string, mixed> $specifications */
        $specifications = $existing->specifications;
        if ($specifications_json !== null && trim($specifications_json) !== '') {
            try {
                $decoded = json_decode($specifications_json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $specifications = $decoded;
                }
            } catch (\JsonException) {
                return $this->json(['error' => 'Specifications must be valid JSON format.']);
            }
        }

        $updated = new Product(
            $existing->slug,
            $name !== null && trim($name) !== '' ? trim($name) : $existing->name,
            $definition !== null && trim($definition) !== '' ? trim($definition) : $existing->definition,
            $category !== null && trim($category) !== '' ? trim($category) : $existing->category,
            $specifications,
            $family_slug !== null && trim($family_slug) !== '' ? trim($family_slug) : $existing->familySlug,
            $family_name !== null && trim($family_name) !== '' ? trim($family_name) : $existing->familyName,
            $image !== null && trim($image) !== '' ? trim($image) : $existing->image,
            $image_credit !== null && trim($image_credit) !== '' ? trim($image_credit) : $existing->imageCredit,
            $image_source !== null && trim($image_source) !== '' ? trim($image_source) : $existing->imageSource,
        );

        if ($this->productRepository !== null) {
            try {
                $this->productRepository->save($updated);
            } catch (\Throwable $e) {
                return $this->json(['error' => 'Failed to update product: ' . $e->getMessage()]);
            }
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Product updated successfully.',
            'product' => [
                'slug' => $updated->slug,
                'name' => $updated->name,
                'category' => $updated->category,
                'family_slug' => $updated->familySlug,
                'family_name' => $updated->familyName,
                'image' => $updated->image,
            ],
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    #[McpTool(
        name: 'delete_polish_fair_price_product',
        description: 'Admin-only: Delete a tracked product from the database. Requires Bearer authorization.'
    )]
    public function deleteProduct(#[Schema(description: 'Product slug to delete.')] string $slug): string
    {
        if ($this->adminAccess?->isGranted() !== true) {
            return $this->json(['error' => 'Unauthorized: Invalid admin token.']);
        }

        $existing = $this->productRepository?->get($slug) ?? $this->catalog->get($slug);
        if ($existing === null) {
            return $this->json(['error' => 'Unknown product slug: ' . $slug]);
        }

        if ($this->productRepository !== null) {
            try {
                $this->productRepository->delete($slug);
            } catch (\Throwable $e) {
                return $this->json(['error' => 'Failed to delete product: ' . $e->getMessage()]);
            }
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Product deleted: ' . $slug,
        ]);
    }

    private function canonicalUrl(string $slug): string
    {
        return 'https://ileza.pl/ceny/' . $slug;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
