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
}
