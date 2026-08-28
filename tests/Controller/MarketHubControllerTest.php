<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Market\Presentation\Http\MarketController;
use App\Shared\Presentation\Http\SitemapController;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordProductRequest;
use App\Market\Application\SubmitCommunityPriceTip;
use App\Market\Application\SubscribePriceAlert;
use App\Market\Application\UnsubscribePriceAlert;
use App\Market\Application\VerifyPriceAlert;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use App\Tests\Market\MockPriceAlertMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class MarketHubControllerTest extends TestCase
{
    public function testHubRendersCategoryView(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribePriceAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyPriceAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribePriceAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/hub.html.twig', self::callback(static function (array $context): bool {
                self::assertSame('laptops', $context['hub']['category']);
                self::assertSame('laptops', $context['category_key']);
                self::assertArrayHasKey('stats', $context);
                self::assertArrayHasKey('matrix', $context);
                self::assertArrayHasKey('movers', $context);
                self::assertArrayHasKey('families', $context);
                self::assertArrayHasKey('hubs', $context);

                return true;
            }))
            ->willReturn('<html>hub</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribePriceAlert,
            $verifyPriceAlert,
            $unsubscribePriceAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->hub('laptopy');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>hub</html>', $response->getContent());
    }

    public function testMacBookHubFiltersToAppleLaptops(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribePriceAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyPriceAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribePriceAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/hub.html.twig', self::callback(static function (array $context): bool {
                self::assertSame('macbook', $context['hub_key']);
                self::assertSame('laptops', $context['category_key']);
                self::assertSame('macbook', $context['hub']['familyFilter']);

                return true;
            }))
            ->willReturn('<html>macbook hub</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribePriceAlert,
            $verifyPriceAlert,
            $unsubscribePriceAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->hub('macbook');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testSitemapContainsAllCategoryHubs(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);

        $sitemapController = new SitemapController($catalog, $observations);
        $response = $sitemapController->__invoke();

        self::assertSame(200, $response->getStatusCode());
        $xml = (string) $response->getContent();

        self::assertStringContainsString('https://ileza.pl/ceny/laptopy', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/laptops', $xml);
        self::assertStringContainsString('https://ileza.pl/ceny/macbook', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/macbook', $xml);
        self::assertStringContainsString('https://ileza.pl/ceny/smartfony', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/smartphones', $xml);
        self::assertStringContainsString('https://ileza.pl/ceny/iphone', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/iphone', $xml);
        self::assertStringContainsString('https://ileza.pl/ceny/ram', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/ram', $xml);
        self::assertStringContainsString('https://ileza.pl/ceny/samochody', $xml);
        self::assertStringContainsString('https://ileza.pl/prices/cars', $xml);
    }

    private function createRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'test-limiter',
            'policy' => 'fixed_window',
            'limit' => 10,
            'interval' => '1 minute',
        ], new InMemoryStorage());
    }
}
