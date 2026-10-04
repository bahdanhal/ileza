<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Market\Application\GetNextPriceResearchBatch;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Capability\Attribute\Schema;

final readonly class PriceResearchTools
{
    public function __construct(
        private AdminAccess $access,
        private GetNextPriceResearchBatch $nextBatch,
    ) {
    }

    /** @param array<array-key, mixed> $exclude_slugs */
    #[McpTool(
        name: 'get_next_price_research_batch',
        title: 'Admin: Next Price Research Batch',
        description: 'Admin-only: Get the next batch of products needing price research (default: 20 products, max: 50), '
            . 'missing prices first, then oldest. Includes definitions, specifications, latest prices, and observation dates. '
            . 'Skips today and yesterday in Poland. Read-only; does not reserve products. Requires Bearer authorization.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    public function nextBatch(
        #[Schema(
            description: 'Slugs explicitly deferred or already assigned elsewhere; keep them in the audit ledger.',
            type: 'array',
            items: ['type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'],
            maxItems: 1000,
        )]
        array $exclude_slugs = [],
        #[Schema(
            description: 'Maximum number of products to return in this batch (default: 20, minimum: 1, maximum: 50).',
            type: 'integer',
            minimum: 1,
            maximum: 50,
        )]
        int $limit = 20,
    ): string {
        return $this->handleBatch($exclude_slugs, $limit);
    }

    /** @param array<array-key, mixed> $exclude_slugs */
    #[McpTool(
        name: 'get_next_polish_fair_price_batch',
        title: 'Admin: Next Price Research Batch (alias)',
        description: 'Admin-only (backward-compatible alias for get_next_price_research_batch): '
            . 'Get the next batch of products needing price research (default: 20 products, max: 50). Requires Bearer authorization.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    public function legacyNextBatch(
        #[Schema(
            description: 'Slugs explicitly deferred or already assigned elsewhere; keep them in the audit ledger.',
            type: 'array',
            items: ['type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'],
            maxItems: 1000,
        )]
        array $exclude_slugs = [],
        #[Schema(
            description: 'Maximum number of products to return in this batch (default: 20, minimum: 1, maximum: 50).',
            type: 'integer',
            minimum: 1,
            maximum: 50,
        )]
        int $limit = 20,
    ): string {
        return $this->handleBatch($exclude_slugs, $limit);
    }

    /** @param array<array-key, mixed> $exclude_slugs */
    private function handleBatch(array $exclude_slugs, int $limit): string
    {
        if (!$this->access->isGranted()) {
            return json_encode(['error' => 'Unauthorized: Invalid admin token.'], JSON_THROW_ON_ERROR);
        }

        if ($limit < 1 || $limit > 50) {
            return json_encode(['error' => 'limit must be an integer between 1 and 50.'], JSON_THROW_ON_ERROR);
        }

        if (!array_is_list($exclude_slugs) || count($exclude_slugs) > 1000) {
            return json_encode(['error' => 'exclude_slugs must be a list of at most 1000 product slugs.'], JSON_THROW_ON_ERROR);
        }
        $validatedSlugs = [];
        foreach ($exclude_slugs as $slug) {
            if (!is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
                return json_encode(['error' => 'exclude_slugs contains an invalid product slug.'], JSON_THROW_ON_ERROR);
            }
            $validatedSlugs[] = $slug;
        }

        return json_encode(
            $this->nextBatch->execute($validatedSlugs, $limit),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
