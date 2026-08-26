<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Application\MarkdownRenderer;
use App\Entity\BlogArticleEntity;
use PHPUnit\Framework\TestCase;

final class BlogContentTest extends TestCase
{
    public function testEntityExposesLocalizedMetadataAndPriceShortcodes(): void
    {
        $article = new BlogArticleEntity(
            'en',
            'used-iphone-buying-checklist',
            'checklista-uzywany-iphone',
            'Used iPhone checklist',
            'A practical checklist.',
            "## Inspect the phone\n\n{{ price(\"iphone-13-128gb\") }}",
            new \DateTimeImmutable('2026-08-25'),
            new \DateTimeImmutable('2026-08-26'),
        );

        self::assertSame('checklista-uzywany-iphone', $article->getAlternateSlug());
        self::assertSame(['iphone-13-128gb'], $article->getPriceSlugs());
        self::assertSame(1, $article->getReadingMinutes());
    }

    public function testMarkdownRendererEscapesHtmlAndRendersSupportedFormatting(): void
    {
        $html = (new MarkdownRenderer())->render("## Heading\n\nA **safe** line <script>alert(1)</script>.\n\n- Item");

        self::assertStringContainsString('<h2>Heading</h2>', $html);
        self::assertStringContainsString('<strong>safe</strong>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<li>Item</li>', $html);
    }
}
