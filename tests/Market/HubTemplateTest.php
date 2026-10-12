<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\BrowseCatalog;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\ProductFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class HubTemplateTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function phoneViews(): iterable
    {
        foreach (['pl', 'en'] as $locale) {
            foreach (['cards', 'matrix'] as $view) {
                yield $locale . ' ' . $view => [$locale, $view];
            }
        }
    }

    #[DataProvider('phoneViews')]
    public function testPhoneFiltersAndFaqAreLocalizedAndViewLinksRetainSelections(string $locale, string $view): void
    {
        $html = $this->renderHub('iphone', $locale, $view, ['model' => 'iPhone 15 Pro', 'storage' => '256 GB']);
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);

        self::assertSame(1, $this->countMatches($xpath, '//select[@name="model"]/option[@selected and @value="iPhone 15 Pro"]'));
        self::assertSame(1, $this->countMatches($xpath, '//select[@name="storage"]/option[@selected and @value="256 GB"]'));
        foreach (['brand', 'ram', 'chip'] as $field) {
            self::assertSame(0, $this->countMatches($xpath, '//select[@name="' . $field . '"]'));
        }
        self::assertSame(1, $this->countMatches($xpath, '//meta[@name="robots" and @content="noindex,follow"]'));
        self::assertStringContainsString($locale === 'pl' ? 'Pytania o wybór' : 'Questions about choosing', $html);
        self::assertStringNotContainsString('market.hubs.iphone_faq', $html);
        self::assertStringNotContainsString('market.facets.price_options', $html);
        $guide = $locale === 'pl' ? '/blog/checklista-uzywany-iphone' : '/en/blog/used-iphone-buying-checklist';
        self::assertSame(1, $this->countMatches($xpath, '//section[@class="tool-faq"]//a[@href="' . $guide . '"]'));
        $links = $xpath->query('//nav[@class="market-view-switcher"]/a');
        self::assertNotFalse($links);
        self::assertSame(2, $links->length);
        foreach ($links as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            self::assertSame('iPhone 15 Pro', $query['model']);
            self::assertSame('256 GB', $query['storage']);
        }
        self::assertSame($view === 'matrix' ? 1 : 0, $this->countMatches($xpath, '//input[@name="view" and @value="matrix"]'));
    }

    public function testNonPhoneHubsKeepRamAndChipFiltersAndTheirExistingFaq(): void
    {
        foreach (['macbook', 'ram'] as $hub) {
            $document = $this->document($this->renderHub($hub, 'en', 'matrix'));
            $xpath = new \DOMXPath($document);
            self::assertSame(1, $this->countMatches($xpath, '//select[@name="ram"]'));
            self::assertSame($hub === 'macbook' ? 1 : 0, $this->countMatches($xpath, '//select[@name="chip"]'));
            self::assertSame(0, $this->countMatches($xpath, '//select[@name="storage" or @name="model"]'));
            self::assertSame(0, $this->countMatches($xpath, '//section[@class="tool-faq"]//a'));
            self::assertSame(2, $this->countMatches($xpath, '//section[@class="tool-faq"]/details'));
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function priceOptionTranslations(): iterable
    {
        foreach (['pl', 'en'] as $locale) {
            foreach (['', 'under-100', '100-2000', '2000-20000', 'over-20000'] as $legacyBand) {
                yield $locale . ' ' . $legacyBand => [$locale, $legacyBand];
            }
        }
    }

    #[DataProvider('priceOptionTranslations')]
    public function testEveryPhonePriceOptionIsTranslatedIncludingLegacyBands(string $locale, string $legacyBand): void
    {
        $html = $this->renderHub('iphone', $locale, 'cards', ['price' => $legacyBand]);
        $xpath = new \DOMXPath($this->document($html));
        $options = $xpath->query('//select[@name="price"]/option[@value!=""]');
        self::assertNotFalse($options);
        self::assertSame($legacyBand === '' ? 6 : 7, $options->length);
        foreach ($options as $option) {
            self::assertInstanceOf(\DOMElement::class, $option);
            self::assertStringNotContainsString('market.facets.price_options', $option->textContent);
            self::assertStringContainsString($locale === 'pl' ? 'zł' : 'PLN', $option->textContent);
        }
    }

    #[DataProvider('phoneViews')]
    public function testFilteredSingletonCardNamesTheExactConfigurationAndLinksToIt(string $locale, string $view): void
    {
        $html = $this->renderHub('iphone', $locale, $view, ['model' => 'iPhone 15 Pro', 'storage' => '256 GB']);
        $xpath = new \DOMXPath($this->document($html));
        self::assertSame(1, $this->countMatches($xpath, '//article[@class="market-product"]'));
        $headings = $xpath->query('//article[@class="market-product"]//h2');
        self::assertNotFalse($headings);
        $heading = $headings->item(0);
        self::assertInstanceOf(\DOMElement::class, $heading);
        self::assertSame('Apple iPhone 15 Pro 256 GB', trim($heading->textContent));
        $path = ($locale === 'pl' ? '/ceny/' : '/prices/') . 'iphone-15-pro-256gb';
        self::assertSame(1, $this->countMatches($xpath, '//article[@class="market-product"]//a[@data-market-link and @href="' . $path . '"]'));
    }

    public function testCardWithMultipleConfigurationsKeepsTheFamilyHeading(): void
    {
        $html = $this->renderHub('iphone', 'en', 'cards', ['model' => 'iPhone 15 Pro']);
        $xpath = new \DOMXPath($this->document($html));
        $headings = $xpath->query('//article[@class="market-product"]//h2');
        self::assertNotFalse($headings);
        self::assertSame(1, $headings->length);
        $heading = $headings->item(0);
        self::assertInstanceOf(\DOMElement::class, $heading);
        self::assertSame('Apple iPhone 15', trim($heading->textContent));
        self::assertGreaterThan(0, $this->countMatches($xpath, '//article[@class="market-product"]//select[@data-market-spec-select="storage"]'));
    }

    /** @param array<string, string> $query */
    private function renderHub(string $key, string $locale, string $view, array $query = []): string
    {
        $catalog = new ProductCatalog();
        $hub = $catalog->resolveHub($key);
        self::assertNotNull($hub);
        $repository = $this->createStub(PriceObservationRepository::class);
        $history = new GetProductPriceHistory($catalog, $repository);
        $history->preload(array_map(static fn ($product): string => $product->slug, $catalog->all()));
        $matrix = $history->categoryMatrix($hub['category'], $hub['familyFilter']);
        $browse = (new BrowseCatalog())->execute($matrix, $query, $hub['category']);
        $families = [];
        foreach ($browse['rows'] as $row) {
            if (!$row['family'] instanceof ProductFamily) {
                continue;
            }
            $slug = $row['family']->slug;
            $families[$slug] ??= ['family' => $row['family'], 'configurations' => []];
            $families[$slug]['configurations'][] = ['product' => $row['product'], 'latest' => $row['latest']];
        }
        $request = new Request();
        $request->setLocale($locale);
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'), ['strict_variables' => true]);
        $twig->addGlobal('app', (object) ['request' => $request]);
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 2) . '/translations/messages.' . $locale . '.yaml', $locale);
        $twig->addFilter(new TwigFilter('trans', $translator->trans(...)));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => 'https://ileza.pl' . $path));
        $twig->addFunction(new TwigFunction('path', static function (string $name, array $parameters = []): string {
            $locale = $parameters['_locale'] ?? 'pl';
            if ($name === 'blog_article') {
                return ($locale === 'pl' ? '/blog/' : '/en/blog/') . $parameters['slug'];
            }
            if ($name === 'market_product') {
                return ($locale === 'pl' ? '/ceny/' : '/prices/') . $parameters['slug'];
            }
            if ($name === 'market_hub') {
                $path = ($locale === 'pl' ? '/ceny/' : '/prices/') . $parameters['category'];
                unset($parameters['_locale'], $parameters['category']);

                return $path . ($parameters === [] ? '' : '?' . http_build_query($parameters));
            }

            return '/' . $name;
        }));

        return $twig->render('market/hub.html.twig', [
            'hub' => $hub,
            'hub_key' => $key,
            'category_key' => $hub['category'],
            'stats' => $history->categoryStatistics($hub['category'], $hub['familyFilter']),
            'matrix' => $browse['rows'],
            'movers' => [],
            'families' => array_values($families),
            'hubs' => $catalog->hubs(),
            'browse' => $browse,
            'view' => $view,
        ]);
    }

    private function countMatches(\DOMXPath $xpath, string $expression): int
    {
        return (int) $xpath->evaluate('count(' . $expression . ')');
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }
}
