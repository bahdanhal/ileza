<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MarketController;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordProductRequest;
use App\Market\Application\SubmitCommunityPriceTip;
use App\Market\Application\SubscribePriceAlert;
use App\Market\Application\UnsubscribePriceAlert;
use App\Market\Application\VerifyPriceAlert;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use App\Tests\Market\MockPriceAlertMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class MarketControllerTest extends TestCase
{
    public function testHomeRendersWithFamilies(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createMock(PriceObservationRepository::class);
        $observations->expects(self::once())
            ->method('histories')
            ->willReturnCallback(static function (array $slugs): array {
                $histories = array_fill_keys($slugs, []);
                foreach (
                    [
                        'macbook-pro-16-m3-max-64-gb-ram-2-tb-ssd' => 900000,
                        'iphone-x-256gb' => 200000,
                        'iphone-x-64gb' => 100000,
                    ] as $slug => $median
                ) {
                    $histories[$slug] = [new PriceObservation(
                        $slug,
                        new \DateTimeImmutable('2026-08-23'),
                        $median,
                        $median - 10000,
                        $median + 10000,
                        'high',
                        '',
                        PriceObservation::METHODOLOGY_MANUAL
                    )];
                }

                return $histories;
            });
        $observations->expects(self::never())->method('history');
        $observations->expects(self::never())->method('latest');
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/home.html.twig', self::callback(static function (array $context): bool {
                self::assertSame('macbook-pro-16-m3-max', $context['families'][0]['family']->slug);
                self::assertSame(
                    'macbook-pro-16-m3-max-64-gb-ram-2-tb-ssd',
                    $context['families'][0]['configurations'][0]['product']->slug
                );

                $iphoneX = array_values(array_filter(
                    $context['families'],
                    static fn (array $item): bool => $item['family']->slug === 'iphone-x'
                ))[0];
                self::assertSame('iphone-x-256gb', $iphoneX['configurations'][0]['product']->slug);

                return true;
            }))
            ->willReturn('<html>home</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->home();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>home</html>', $response->getContent());
    }

    public function testLegacyHomeRedirectsPermanently(): void
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
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(function ($name, $params) {
            return ($params['_locale'] === 'pl' ? '/' : '/en/');
        });

        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $request = new Request();
        $request->setLocale('pl');

        $response = $controller->legacyHome($request);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    public function testProductRendersDetailedHistory(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createMock(PriceObservationRepository::class);
        $observations->expects(self::once())->method('history')->with('iphone-13-128gb')->willReturn([
            new PriceObservation(
                'iphone-13-128gb',
                new \DateTimeImmutable('2026-08-20'),
                210000,
                190000,
                230000,
                'high',
                '',
                PriceObservation::METHODOLOGY_MANUAL
            ),
        ]);

        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);
        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/product.html.twig', self::callback(static function (array $context): bool {
                return $context['product']->slug === 'iphone-13-128gb'
                    && $context['latest'] !== null;
            }))
            ->willReturn('<html>product</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->product('iphone-13-128gb');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestProductSavesSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createMock(ProductRequestStore::class);
        $productRequests->expects(self::once())
            ->method('save')
            ->with('iPhone 15 Pro', 'test@example.com', '127.0.0.1');

        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Saved');

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $container = new Container();
        $controller->setContainer($container);

        $request = new Request(
            request: ['product' => 'iPhone 15 Pro', 'email' => 'test@example.com'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->requestProduct($request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testSubmitPriceTipSavesSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createMock(PriceTipRepository::class);
        $priceTips->expects(self::once())
            ->method('submit')
            ->with('iphone-13-128gb', 'https://allegro.pl/oferta/test-123', 'tip@example.com', '127.0.0.1')
            ->willReturn(new PriceTip(
                'iphone-13-128gb',
                'https://allegro.pl/oferta/test-123',
                'tip@example.com',
                'hash',
                new \DateTimeImmutable(),
                new \DateTimeImmutable('+90 days')
            ));

        $priceAlerts = $this->createStub(PriceAlertRepository::class);
        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Saved');

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $container = new Container();
        $controller->setContainer($container);

        $request = new Request(
            request: ['listing_url' => 'https://allegro.pl/oferta/test-123', 'email' => 'tip@example.com'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->submitPriceTip('iphone-13-128gb', $request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testSubscribeAlertEndpointSavesSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createMock(PriceAlertRepository::class);
        $priceAlerts->expects(self::once())
            ->method('subscribe')
            ->with('iphone-13-128gb', 'user@example.com', 200000, '127.0.0.1', 'pl')
            ->willReturn(new PriceAlert(
                1,
                'iphone-13-128gb',
                'user@example.com',
                200000,
                'token123',
                'unsub123',
                false,
                null,
                'hash',
                'pl',
                new \DateTimeImmutable()
            ));

        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Alert saved');

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();

        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $container = new Container();
        $controller->setContainer($container);

        $request = new Request(
            request: ['email' => 'user@example.com', 'target_price' => '2000'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );
        $request->setLocale('pl');

        $response = $controller->subscribeAlert('iphone-13-128gb', $request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testVerifyAlertEndpointRendersSuccess(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createMock(PriceAlertRepository::class);
        $priceAlerts->expects(self::once())
            ->method('markVerified')
            ->with('validtoken123')
            ->willReturn(new PriceAlert(
                1,
                'iphone-13-128gb',
                'user@example.com',
                200000,
                'validtoken123',
                'unsub123',
                true,
                new \DateTimeImmutable(),
                'hash',
                'pl',
                new \DateTimeImmutable(),
                status: PriceAlert::STATUS_ACTIVE
            ));

        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/alert_verified.html.twig', self::callback(static function (array $context): bool {
                return $context['alert']->isVerified && $context['product']->slug === 'iphone-13-128gb';
            }))
            ->willReturn('<html>verified</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->verifyAlert('validtoken123');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>verified</html>', $response->getContent());
    }

    public function testUnsubscribeAlertEndpointRendersSuccess(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceAlerts = $this->createMock(PriceAlertRepository::class);
        $priceAlerts->expects(self::once())
            ->method('markUnsubscribed')
            ->with('unsubtoken123')
            ->willReturn(new PriceAlert(
                1,
                'iphone-13-128gb',
                'user@example.com',
                200000,
                'validtoken123',
                'unsubtoken123',
                false,
                null,
                'hash',
                'pl',
                new \DateTimeImmutable(),
                status: PriceAlert::STATUS_UNSUBSCRIBED
            ));

        $mailer = new MockPriceAlertMailer();
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();
        $alertLimiter = $this->createRateLimiterFactory();
        $subscribeAlert = new SubscribePriceAlert($catalog, $priceAlerts, $mailer);
        $verifyAlert = new VerifyPriceAlert($priceAlerts);
        $unsubscribeAlert = new UnsubscribePriceAlert($priceAlerts);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/alert_unsubscribed.html.twig', self::callback(static function (array $context): bool {
                return $context['alert']->status === PriceAlert::STATUS_UNSUBSCRIBED && $context['product']->slug === 'iphone-13-128gb';
            }))
            ->willReturn('<html>unsubscribed</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $subscribeAlert,
            $verifyAlert,
            $unsubscribeAlert,
            $alertLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->unsubscribeAlert('unsubtoken123');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>unsubscribed</html>', $response->getContent());
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
