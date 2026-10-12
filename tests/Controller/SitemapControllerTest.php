<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Content\Application\BlogArticleRepository;
use App\Content\Domain\BlogArticle;
use App\Shared\Presentation\Http\SitemapController;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    public function testIncludesActualArticlesWithoutInventingMissingTranslations(): void
    {
        $createArticle = static fn (string $locale, string $slug, string $alternate, string $updated): BlogArticle => new BlogArticle(
            $locale,
            $slug,
            $alternate,
            'Guide',
            'Description',
            'Body',
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable($updated),
        );
        $polish = $createArticle('pl', 'polish-guide', 'english-guide', '2026-09-02');
        $english = $createArticle('en', 'english-guide', 'polish-guide', '2026-09-03');
        $untranslatedPl = $createArticle('pl', 'polish-only', 'missing-english', '2026-09-04');
        $untranslatedEn = $createArticle('en', 'english-only', 'missing-polish', '2026-09-05');
        $articles = $this->createStub(BlogArticleRepository::class);
        $articles->method('all')->willReturnCallback(
            static fn (string $locale): array => $locale === 'pl' ? [$polish, $untranslatedPl] : [$english, $untranslatedEn]
        );
        $observations = $this->createStub(PriceObservationRepository::class);

        $content = (string) (new SitemapController(new ProductCatalog(), $observations, $articles))()->getContent();
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($content));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');
        self::assertStringNotContainsString('missing-english', $content);
        self::assertStringNotContainsString('missing-polish', $content);
        $expectedEntries = [
            '/blog/polish-guide' => '2026-09-02',
            '/en/blog/english-guide' => '2026-09-03',
            '/blog/polish-only' => '2026-09-04',
            '/en/blog/english-only' => '2026-09-05',
        ];
        foreach ($expectedEntries as $path => $date) {
            $entry = '/s:urlset/s:url[s:loc="https://ileza.pl' . $path . '"]';
            $entries = $xpath->query($entry);
            self::assertNotFalse($entries);
            self::assertCount(1, $entries);
            self::assertSame($date, $xpath->evaluate('string(' . $entry . '/s:lastmod)'));
            $alternates = $xpath->query($entry . '/h:link');
            self::assertNotFalse($alternates);
            self::assertCount(str_ends_with($path, '-only') ? 0 : 3, $alternates);
        }
    }

    public function testGeneratesValidXmlSitemapWithHeaders(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('latest')->willReturn(null);

        $controller = new SitemapController($catalog, $observations);
        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame(300, $response->getMaxAge());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));

        $content = (string) $response->getContent();
        self::assertStringContainsString('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/</loc>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/en/</loc>', $content);
        self::assertStringContainsString('hreflang="x-default" href="https://ileza.pl/"', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/salary-calculator</loc>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/kalkulator-wynagrodzen</loc>', $content);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($content));
    }

    public function testIncludesProductsWithObservationsAndLastmod(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('latest')->willReturnCallback(static function (string $slug): ?PriceObservation {
            if ($slug === 'peugeot-206-cc-1-6-petrol') {
                return new PriceObservation(
                    'peugeot-206-cc-1-6-petrol',
                    new \DateTimeImmutable('2026-08-21T12:00:00+02:00'),
                    1200000,
                    1000000,
                    1400000,
                    'medium',
                    '',
                    PriceObservation::METHODOLOGY_MANUAL,
                );
            }

            return null;
        });

        $controller = new SitemapController($catalog, $observations);
        $response = $controller();

        $content = (string) $response->getContent();
        $expectedUrl = 'https://ileza.pl/prices/peugeot-206-cc-1-6-petrol';
        $expectedPlUrl = 'https://ileza.pl/ceny/peugeot-206-cc-1-6-petrol';
        self::assertStringContainsString($expectedUrl, $content);
        self::assertStringContainsString($expectedPlUrl, $content);
        self::assertStringContainsString('<lastmod>2026-08-21</lastmod>', $content);
        self::assertStringContainsString('peugeot-206-cc-2-0-petrol', $content);
    }
}
