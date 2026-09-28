<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Market\Application\GetNextPriceResearchBatch;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use App\Mcp\AdminAccess;
use App\Mcp\PriceResearchTools;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PriceResearchToolsTest extends TestCase
{
    private function createTools(bool $authenticated = true): PriceResearchTools
    {
        $requestStack = new RequestStack();
        $request = new Request();
        if ($authenticated) {
            $request->headers->set('Authorization', 'Bearer test-admin-token');
        }
        $requestStack->push($request);

        $adminAccess = new AdminAccess($requestStack, 'test-admin-token');
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('histories')->willReturn([]);

        $service = new GetNextPriceResearchBatch($catalog, $repository);

        return new PriceResearchTools($adminAccess, $service);
    }

    public function testUnauthorizedWhenNoTokenProvided(): void
    {
        $tools = $this->createTools(authenticated: false);

        $response = json_decode($tools->nextBatch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $response);
        self::assertStringContainsString('Unauthorized', $response['error']);

        $legacyResponse = json_decode($tools->legacyNextBatch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $legacyResponse);
        self::assertStringContainsString('Unauthorized', $legacyResponse['error']);
    }

    public function testNextBatchReturnsTwentyProductsByDefault(): void
    {
        $tools = $this->createTools();

        $response = json_decode($tools->nextBatch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('error', $response);
        self::assertSame(20, $response['batch_size']);
        self::assertCount(20, $response['products']);
        self::assertTrue($response['has_more']);
        self::assertArrayHasKey('queue_semantics', $response);
    }

    public function testLegacyNextBatchBehavesIdentically(): void
    {
        $tools = $this->createTools();

        $response = json_decode($tools->legacyNextBatch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('error', $response);
        self::assertSame(20, $response['batch_size']);
        self::assertCount(20, $response['products']);
    }

    public function testCustomLimitParameter(): void
    {
        $tools = $this->createTools();

        $response = json_decode($tools->nextBatch(limit: 7), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('error', $response);
        self::assertSame(7, $response['batch_size']);
        self::assertCount(7, $response['products']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $tools = $this->createTools();

        $zero = json_decode($tools->nextBatch(limit: 0), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $zero);
        self::assertStringContainsString('limit must be an integer between 1 and 50', $zero['error']);

        $tooHigh = json_decode($tools->nextBatch(limit: 51), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $tooHigh);
        self::assertStringContainsString('limit must be an integer between 1 and 50', $tooHigh['error']);
    }

    public function testValidatesExcludeSlugs(): void
    {
        $tools = $this->createTools();

        $invalid = json_decode(
            $tools->nextBatch(exclude_slugs: ['Invalid_Slug!']),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertArrayHasKey('error', $invalid);
        self::assertStringContainsString('exclude_slugs contains an invalid product slug', $invalid['error']);
    }
}
