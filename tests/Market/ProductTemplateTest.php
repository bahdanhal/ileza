<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ProductTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $this->twig = new Environment($loader);
        $this->twig->addFilter(new TwigFilter('trans', static fn (string $id, array $params = []): string => strtr($id, $params)));
        $this->twig->addFunction(new TwigFunction('path', static fn (string $name, array $params = []): string => '/' . $name));

        $request = new Request();
        $request->setLocale('pl');
        $app = new \stdClass();
        $app->request = $request;
        $this->twig->addGlobal('app', $app);
    }

    public function testRendersProductWhenPreviousObservationIsUnavailableWithZeroMedian(): void
    {
        $product = new Product(
            slug: 'macbook-air-13-m3-24-gb-ram-512-gb-ssd',
            name: 'Apple MacBook Air 13 M3 24 GB 512 GB',
            definition: 'Laptop Apple',
            category: 'laptopy',
            specifications: ['RAM' => '24 GB', 'SSD' => '512 GB'],
            familySlug: 'macbook-air-m3',
            familyName: 'MacBook Air M3',
        );

        $family = new ProductFamily(
            slug: 'macbook-air-m3',
            name: 'MacBook Air M3',
            category: 'laptopy',
            image: '/images/macbook.webp',
            imageCredit: 'Test',
            imageSource: 'https://example.com',
            configurations: [$product],
        );

        $latest = new PriceObservation(
            'macbook-air-13-m3-24-gb-ram-512-gb-ssd',
            new \DateTimeImmutable('2026-09-17'),
            550000,
            500000,
            600000,
            'available',
            'Cheapest available offer',
            PriceObservation::METHODOLOGY_MANUAL,
        );

        $unavailablePrevious = new PriceObservation(
            'macbook-air-13-m3-24-gb-ram-512-gb-ssd',
            new \DateTimeImmutable('2026-09-10'),
            0,
            0,
            0,
            'unavailable',
            'No offer found',
            PriceObservation::METHODOLOGY_MANUAL,
        );

        $html = $this->twig->render('market/product.html.twig', [
            'product' => $product,
            'family' => $family,
            'category_slug' => 'laptopy',
            'category_name' => 'Laptopy',
            'canonical_url' => 'https://ileza.pl/ceny/macbook-air-13-m3-24-gb-ram-512-gb-ssd',
            'latest' => $latest,
            'history' => [$latest, $unavailablePrevious],
            'one_month_ago' => $unavailablePrevious,
            'related' => [],
        ]);

        self::assertStringContainsString('5 500,00 zł', $html);
        self::assertStringContainsString('market.asking_price', $html);
        self::assertStringContainsString('market.no_month_data', $html);
        self::assertStringContainsString('market.unavailable_short', $html);
        self::assertStringContainsString('market.unavailable.label', $html);
    }
}
