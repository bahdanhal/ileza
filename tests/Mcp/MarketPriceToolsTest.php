<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Mcp\AdminAccess;
use App\Mcp\MarketPriceTools;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class MarketPriceToolsTest extends TestCase
{
    public function testListProductsReturnsJson(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
        );

        $json = $tools->listProducts();
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('products', $data);
        self::assertNotEmpty($data['products']);
    }

    public function testGetHistoryReturnsHistory(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('history')->willReturn([
            new PriceObservation(
                'iphone-13-128gb',
                new \DateTimeImmutable('2026-08-22'),
                95000,
                83600,
                108300,
                'high',
                'Verified fair price',
                'Methodology note'
            ),
        ]);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
        );
        $json = $tools->getHistory('iphone-13-128gb');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('iphone-13-128gb', $data['product']['slug']);
        self::assertEquals(950, $data['observations'][0]['fair_price_pln']);
    }

    public function testAdminUpdateObservationUnauthorized(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer wrong-token');
        $requestStack->push($request);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationFailsClosedWhenUnconfigured(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess(new RequestStack(), ''),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationFailsWithoutHeader(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess(new RequestStack(), 'test-token-123'),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationSavesSuccessfullyWithBearerToken(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())->method('save');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $requestStack->push($request);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );
        $json = $tools->updateObservation(
            'iphone-13-128gb',
            950,
            830,
            1080,
            'available',
            '2026-08-22',
            'Personal verification'
        );

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $data['status']);
        self::assertEquals(950, $data['observation']['fair_price_pln']);
        self::assertEquals(950, $data['observation']['reasonable_high_pln']);
    }

    public function testAdminUpdateObservationSavesSuccessfullyWithAuthorizationHeader(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())->method('save');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $requestStack->push($request);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );
        $json = $tools->updateObservation(
            'iphone-13-128gb',
            950,
            830,
            1080,
            'available',
            '2026-08-22',
            'Personal verification'
        );

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $data['status']);
        self::assertEquals(950, $data['observation']['fair_price_pln']);
    }

    public function testGetProductReturnsProductData(): void
    {
        $catalog = new ProductCatalog();
        $obsRepo = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $obsRepo),
            new RecordPriceObservation($catalog, $obsRepo),
        );

        $json = $tools->getProduct('iphone-13-128gb');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('product', $data);
        self::assertSame('iphone-13-128gb', $data['product']['slug']);
        self::assertSame('smartphones', $data['product']['category']);
    }

    public function testAdminCreateUpdateDeleteProductFlow(): void
    {
        $catalog = new ProductCatalog();
        $obsRepo = $this->createStub(PriceObservationRepository::class);
        $prodRepo = $this->createMock(\App\Market\Domain\ProductRepository::class);
        $prodRepo->expects(self::exactly(2))->method('save');
        $prodRepo->expects(self::once())->method('delete')->with('custom-test-product');
        $prodRepo->method('get')->willReturnCallback(static function (string $slug): ?\App\Market\Domain\Product {
            if ($slug === 'custom-test-product') {
                return new \App\Market\Domain\Product(
                    'custom-test-product',
                    'Custom Test Product',
                    'Clean definition test',
                    'smartphones',
                );
            }
            return null;
        });

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $requestStack->push($request);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $obsRepo),
            new RecordPriceObservation($catalog, $obsRepo),
            new AdminAccess($requestStack, 'test-token-123'),
            $prodRepo,
        );

        // Create
        $createJson = $tools->createProduct(
            'custom-test-product',
            'Custom Test Product',
            'smartphones',
            'Clean definition test',
            'custom-family',
            'Custom Family',
            '/images/market/test.jpg',
            'Test Author',
            'https://example.com/source',
            '{"storage":"256 GB"}'
        );
        $createData = json_decode($createJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $createData['status']);
        self::assertSame('custom-test-product', $createData['product']['slug']);

        // Update
        $updateJson = $tools->updateProduct(
            'iphone-13-128gb',
            name: 'Updated iPhone 13 128GB'
        );
        $updateData = json_decode($updateJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $updateData['status']);

        // Delete
        $deleteJson = $tools->deleteProduct('custom-test-product');
        $deleteData = json_decode($deleteJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $deleteData['status']);
    }
}
