<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Content\Application\BlogArticleRepository;
use App\Content\Domain\BlogArticle;
use App\Entity\BlogArticleEntity;
use App\Market\Application\GetMarketStatistics;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use Bahdan\LeadCaptureBundle\Domain\Lead;
use Bahdan\LeadCaptureBundle\Domain\LeadRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class AdminTools
{
    public function __construct(
        private AdminAccess $access,
        private LeadRepository $leads,
        private ProductRequestStore $productRequests,
        private PriceTipRepository $priceTips,
        private GetMarketStatistics $marketStatistics,
        private TrafficAnalytics $trafficAnalytics,
        private BlogArticleRepository $blogArticles,
    ) {
    }

    #[McpTool(
        name: 'get_admin_dashboard_statistics',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Get privacy-preserving traffic, submission, and market-coverage statistics. Requires an Authorization: Bearer header.'
    )]
    public function statistics(): string
    {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $leads = $this->leads->all();
        $requests = $this->productRequests->all();
        $tips = $this->priceTips->all();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');
        $thirtyDaysAgo = $now->modify('-30 days');
        $market = $this->marketStatistics->calculate($now);

        return $this->json([
            'generated_at' => $now->format(DATE_ATOM),
            'submissions' => [
                'contact_leads' => $this->submissionStats(
                    $leads,
                    static fn (Lead $lead): \DateTimeImmutable => $lead->createdAt,
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
                'product_requests' => $this->submissionStats(
                    $requests,
                    static fn (array $request): \DateTimeImmutable => new \DateTimeImmutable($request['timestamp']),
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
                'active_price_tips' => $this->submissionStats(
                    $tips,
                    static fn (PriceTip $tip): \DateTimeImmutable => $tip->submittedAt,
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
            ],
            'traffic' => $this->trafficAnalytics->summary($now),
            'lead_sources' => $this->frequencies(array_map(static fn (Lead $lead): string => $lead->source, $leads)),
            'requested_products' => $this->frequencies(array_map(
                static fn (array $request): string => $request['product'],
                $requests,
            )),
            'price_tip_products' => $this->frequencies(array_map(
                static fn (PriceTip $tip): string => $tip->productSlug,
                $tips,
            )),
            'market_coverage' => [
                'tracked_products' => $market['tracked_products'],
                'products_with_history' => $market['products_with_history'],
                'observation_points' => $market['observation_points'],
                'products_without_history' => $market['products_without_history'],
                'products_not_reviewed_in_30_days' => $market['stale_products'],
            ],
        ]);
    }

    #[McpTool(
        name: 'list_admin_contact_leads',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List recent private consultation requests, including contact details and messages. Requires an Authorization: Bearer header.'
    )]
    public function contactLeads(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->leads->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_map(static fn (Lead $lead): array => [
                'created_at' => $lead->createdAt->format(DATE_ATOM),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'message' => $lead->message,
                'source' => $lead->source,
            ], array_slice($all, 0, $limit)),
        ]);
    }

    #[McpTool(
        name: 'list_admin_product_requests',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List recent unfulfilled product tracking requests from the community. Requires an Authorization: Bearer header.'
    )]
    public function productRequests(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->productRequests->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_slice($all, 0, $limit),
        ]);
    }

    #[McpTool(
        name: 'list_admin_price_tips',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List active community-submitted public listing links awaiting editorial price review. Requires an Authorization: Bearer header.'
    )]
    public function priceTips(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->priceTips->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_map(static fn (PriceTip $tip): array => [
                'submitted_at' => $tip->submittedAt->format(DATE_ATOM),
                'expires_at' => $tip->expiresAt->format(DATE_ATOM),
                'product_slug' => $tip->productSlug,
                'listing_url' => $tip->listingUrl,
                'email' => $tip->email,
            ], array_slice($all, 0, $limit)),
            'privacy' => 'Admin-only review material. Never fetch automatically, log, or republish these URLs.',
        ]);
    }

    #[McpTool(
        name: 'list_admin_blog_articles',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List buying guides and blog articles across locales with metadata, word counts, and price widget dependencies. Requires an Authorization: Bearer header.'
    )]
    public function blogArticles(
        #[Schema(description: 'Filter by locale ("pl" or "en"), or omit for all.')] ?string $locale = null,
        #[Schema(description: 'Maximum articles to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $articles = $this->blogArticles->findAllForAdmin($locale);

        return $this->json([
            'total' => count($articles),
            'returned' => min($limit, count($articles)),
            'items' => array_map(
                static fn (BlogArticle $article): array => [
                    'id' => $article->getId(),
                    'locale' => $article->getLocale(),
                    'slug' => $article->getSlug(),
                    'alternate_slug' => $article->getAlternateSlug(),
                    'title' => $article->getTitle(),
                    'description' => $article->getDescription(),
                    'reading_minutes' => $article->getReadingMinutes(),
                    'price_slugs' => $article->getPriceSlugs(),
                    'published_at' => $article->getPublishedAt()->format(DATE_ATOM),
                    'updated_at' => $article->getUpdatedAt()->format(DATE_ATOM),
                    'url' => ($article->getLocale() === 'en' ? '/en/blog/' : '/blog/') . $article->getSlug(),
                ],
                array_slice($articles, 0, $limit),
            ),
        ]);
    }

    #[McpTool(
        name: 'get_admin_blog_article',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Get full buying guide / blog article markdown and price widget metadata by slug and locale. Requires an Authorization: Bearer header.'
    )]
    public function getBlogArticle(
        #[Schema(description: 'URL slug of the article.')] string $slug,
        #[Schema(description: 'Locale of the article ("pl" or "en"). Default: "pl".')] string $locale = 'pl',
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $entity = $this->blogArticles->findEntityByLocaleAndSlug($locale, $slug);
        if ($entity === null) {
            return $this->json(['error' => 'Article not found for given slug and locale.']);
        }

        return $this->json([
            'id' => $entity->getId(),
            'locale' => $entity->getLocale(),
            'slug' => $entity->getSlug(),
            'alternate_slug' => $entity->getAlternateSlug(),
            'title' => $entity->getTitle(),
            'description' => $entity->getDescription(),
            'body_markdown' => $entity->getBodyMarkdown(),
            'reading_minutes' => $entity->getReadingMinutes(),
            'price_slugs' => $entity->getPriceSlugs(),
            'published_at' => $entity->getPublishedAt()->format(DATE_ATOM),
            'updated_at' => $entity->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }

    #[McpTool(
        name: 'save_admin_blog_article',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Create or update a buying guide / blog article with automatic smart-character cleanup and plain ASCII normalization. Requires an Authorization: Bearer header.'
    )]
    public function saveBlogArticle(
        #[Schema(description: 'Language locale ("pl" or "en").')] string $locale,
        #[Schema(description: 'URL slug of the article.')] string $slug,
        #[Schema(description: 'Article headline / title.')] string $title,
        #[Schema(description: 'Short summary or lead description.')] string $description,
        #[Schema(description: 'Full body Markdown content.')] string $body_markdown,
        #[Schema(description: 'Paired alternate language slug.')] string $alternate_slug = '',
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $locale = strtolower(trim($locale));
        $slug = strtolower(trim($slug));
        $alternate_slug = strtolower(trim($alternate_slug));
        $title = $this->cleanSymbols(trim($title));
        $description = $this->cleanSymbols(trim($description));
        $body_markdown = $this->cleanSymbols(trim($body_markdown));

        if (!in_array($locale, ['en', 'pl'], true)) {
            return $this->json(['error' => 'Locale must be "pl" or "en".']);
        }

        if ($slug === '' || $title === '' || $body_markdown === '') {
            return $this->json(['error' => 'slug, title, and body_markdown cannot be empty.']);
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $entity = $this->blogArticles->findEntityByLocaleAndSlug($locale, $slug);
        $isNew = false;

        try {
            if ($entity === null) {
                $isNew = true;
                $entity = new BlogArticleEntity(
                    $locale,
                    $slug,
                    $alternate_slug,
                    $title,
                    $description,
                    $body_markdown,
                    $now,
                    $now
                );
            } else {
                $entity->setAlternateSlug($alternate_slug);
                $entity->setTitle($title);
                $entity->setDescription($description);
                $entity->setBodyMarkdown($body_markdown);
                $entity->setUpdatedAt($now);
            }

            $this->blogArticles->save($entity);

            return $this->json([
                'status' => 'success',
                'action' => $isNew ? 'created' : 'updated',
                'id' => $entity->getId(),
                'locale' => $entity->getLocale(),
                'slug' => $entity->getSlug(),
                'title' => $entity->getTitle(),
                'url' => ($entity->getLocale() === 'en' ? '/en/blog/' : '/blog/') . $entity->getSlug(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Failed to save article: ' . $e->getMessage()]);
        }
    }

    #[McpTool(
        name: 'delete_admin_blog_article',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Delete a buying guide / blog article by locale and slug. Requires an Authorization: Bearer header.'
    )]
    public function deleteBlogArticle(
        #[Schema(description: 'Locale of the article ("pl" or "en").')] string $locale,
        #[Schema(description: 'URL slug of the article to delete.')] string $slug,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $entity = $this->blogArticles->findEntityByLocaleAndSlug($locale, $slug);
        if ($entity === null) {
            return $this->json(['error' => 'Article not found.']);
        }

        $this->blogArticles->delete($entity);

        return $this->json([
            'status' => 'success',
            'action' => 'deleted',
            'locale' => $locale,
            'slug' => $slug,
        ]);
    }

    private function cleanSymbols(string $text): string
    {
        $replacements = [
            "\u{2014}" => '-',
            "\u{2013}" => '-',
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{00A0}" => ' ',
        ];

        return strtr($text, $replacements);
    }

    /**
     * @param list<mixed> $items
     * @return array{total: int, last_7_days: int, last_30_days: int}
     */
    private function submissionStats(
        array $items,
        callable $date,
        \DateTimeImmutable $sevenDaysAgo,
        \DateTimeImmutable $thirtyDaysAgo
    ): array {
        return [
            'total' => count($items),
            'last_7_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $sevenDaysAgo)),
            'last_30_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $thirtyDaysAgo)),
        ];
    }

    /**
     * @param list<string> $values
     * @return array<string, int>
     */
    private function frequencies(array $values): array
    {
        $counts = array_count_values(array_filter($values, static fn (string $value): bool => $value !== ''));
        arsort($counts);

        return array_slice($counts, 0, 10, true);
    }

    private function validLimit(int $limit): bool
    {
        return $limit >= 1 && $limit <= 100;
    }

    private function unauthorized(): string
    {
        return $this->json(['error' => 'Unauthorized: valid admin Bearer token required.']);
    }

    private function invalidLimit(): string
    {
        return $this->json(['error' => 'Limit must be between 1 and 100.']);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
