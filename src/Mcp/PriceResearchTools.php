<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Market\Application\GetNextPriceResearchBatch;
use Mcp\Capability\Attribute\McpTool;
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
        name: 'get_next_polish_fair_price_batch',
        description: 'Admin-only: Get the next 10 products needing price research, missing prices first, then oldest. '
            . 'Includes definitions, specifications, latest prices and dates. Skips today and yesterday in Poland. '
            . 'Read-only; does not reserve products. Requires Bearer authorization.'
    )]
    public function nextBatch(
        #[Schema(
            description: 'Slugs explicitly deferred or already assigned elsewhere; keep them in the audit ledger.',
            type: 'array',
            items: ['type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'],
            maxItems: 1000,
        )]
        array $exclude_slugs = [],
    ): string {
        if (!$this->access->isGranted()) {
            return json_encode(['error' => 'Unauthorized: Invalid admin token.'], JSON_THROW_ON_ERROR);
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
            $this->nextBatch->execute($validatedSlugs),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
