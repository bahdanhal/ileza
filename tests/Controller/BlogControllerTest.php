<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Content\Application\BlogArticleRepository;
use App\Content\Application\MarkdownRenderer;
use App\Content\Domain\BlogArticle;
use App\Content\Presentation\Http\BlogController;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class BlogControllerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function translations(): iterable
    {
        foreach (['pl', 'en'] as $locale) {
            foreach (['paired', 'missing', 'mismatched'] as $state) {
                yield $locale . ' ' . $state => [$locale, $state];
            }
        }
    }

    #[DataProvider('translations')]
    public function testOnlyPublishedReciprocalTranslationsAreAdvertised(string $locale, string $state): void
    {
        $otherLocale = $locale === 'pl' ? 'en' : 'pl';
        $date = new \DateTimeImmutable('2026-09-01');
        $article = new BlogArticle($locale, 'current-guide', 'translated-guide', 'Guide', 'Description', 'Body', $date, $date);
        $alternate = $state === 'missing' ? null : new BlogArticle(
            $otherLocale,
            'translated-guide',
            $state === 'paired' ? 'current-guide' : 'different-guide',
            'Guide',
            'Description',
            'Body',
            $date,
            $date,
        );
        $repository = $this->createStub(BlogArticleRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (string $language, string $slug): ?BlogArticle => $language === $locale && $slug === 'current-guide' ? $article : $alternate
        );

        $request = new Request();
        $request->setLocale($locale);
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'), ['strict_variables' => true]);
        $twig->addGlobal('app', (object) ['request' => $request]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $id, array $parameters = []): string => strtr($id, $parameters)));
        $twig->addFunction(new TwigFunction('path', static function (string $name, array $parameters = []): string {
            if (in_array($name, ['blog_index', 'blog_article'], true)) {
                return ($parameters['_locale'] === 'pl' ? '/blog/' : '/en/blog/') . ($parameters['slug'] ?? '');
            }

            return '/' . $name;
        }));
        $catalog = new ProductCatalog();
        $controller = new BlogController(
            $repository,
            new MarkdownRenderer(),
            $catalog,
            new GetProductPriceHistory($catalog, $this->createStub(PriceObservationRepository::class))
        );
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);
        $response = $controller->article('current-guide', $request);
        self::assertSame(200, $response->getStatusCode());

        $document = new \DOMDocument();
        @$document->loadHTML((string) $response->getContent());
        $xpath = new \DOMXPath($document);
        $currentPath = ($locale === 'pl' ? '/blog/' : '/en/blog/') . 'current-guide';
        $otherPath = ($otherLocale === 'pl' ? '/blog/' : '/en/blog/') . ($state === 'paired' ? 'translated-guide' : '');
        self::assertSame('https://ileza.pl' . $currentPath, $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
        $alternates = $xpath->query('//link[@hreflang]');
        self::assertNotFalse($alternates);
        self::assertCount($state === 'paired' ? 3 : 0, $alternates);
        self::assertSame($otherPath, $xpath->evaluate('string(//a[@class="language-switch"]/@href)'));
        if ($state !== 'paired') {
            self::assertStringNotContainsString('translated-guide', (string) $response->getContent());
        }
    }
}
