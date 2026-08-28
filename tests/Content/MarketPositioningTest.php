<?php

declare(strict_types=1);

namespace App\Tests\Content;

use PHPUnit\Framework\TestCase;

final class MarketPositioningTest extends TestCase
{
    public function testProductPagesDescribeEditorialPriceHistoryWithoutOfferMarkup(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/market/product.html.twig');

        self::assertStringContainsString("'@type':'Dataset'", $template);
        self::assertStringContainsString("'measurementTechnique'", $template);
        self::assertStringNotContainsString("'@type':'Product'", $template);
        self::assertStringNotContainsString('AggregateOffer', $template);
        self::assertStringNotContainsString('sampleSize', $template);
    }

    public function testCategoryPagesLinkToPriceHistoryPagesWithoutProductOffers(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/market/hub.html.twig');

        self::assertStringContainsString("'@type': 'WebPage'", $template);
        self::assertStringNotContainsString("'@type': 'Product'", $template);
        self::assertStringNotContainsString('AggregateOffer', $template);
        self::assertStringNotContainsString('sampleSize', $template);
    }

    public function testPriceTemplatesDoNotExposeEscapedHtmlEntities(): void
    {
        $templateDirectory = dirname(__DIR__, 2) . '/templates';
        $templates = [
            $templateDirectory . '/market/home.html.twig',
            $templateDirectory . '/market/hub.html.twig',
            $templateDirectory . '/market/product.html.twig',
            $templateDirectory . '/blog/article.html.twig',
            $templateDirectory . '/blog/_price_badge.html.twig',
        ];

        foreach ($templates as $template) {
            self::assertStringNotContainsString('&nbsp;', (string) file_get_contents($template), $template);
        }
    }
}
