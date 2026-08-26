<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Analytics\Infrastructure\DoctrinePageViewRepository;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use App\Lead\Infrastructure\DoctrineLeadRepository;
use App\Market\Application\GetMarketStatistics;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use App\Market\Infrastructure\DoctrinePriceObservationRepository;
use App\Market\Infrastructure\DoctrinePriceTipRepository;
use App\Market\Infrastructure\DoctrineProductRequestStore;
use App\Mcp\AdminAccess;
use App\Mcp\AdminTools;
use App\Tests\DoctrineTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminToolsTest extends DoctrineTestCase
{
    public function testAllAdminToolsFailClosedWithoutBearerToken(): void
    {
        $tools = $this->tools(false);

        $responses = [
            $tools->statistics(),
            $tools->contactLeads(),
            $tools->productRequests(),
            $tools->priceTips(),
        ];
        foreach ($responses as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Unauthorized', $data['error']);
        }
    }

    public function testAdminCanReadSubmissionsAndAggregateStatistics(): void
    {
        $leadRepository = new DoctrineLeadRepository($this->entityManager);
        $leadRepository->save(Lead::create(
            'person@example.com',
            '+48 500 000 000',
            'Please review my workflow.',
            'hashed-ip',
            'landing',
        ));

        $productRequests = new DoctrineProductRequestStore($this->entityManager, 'secret');
        $productRequests->save('PlayStation 5 Slim', 'person@example.com', '198.51.100.5');

        $priceTips = new DoctrinePriceTipRepository($this->entityManager, 'secret');
        $priceTips->submit(
            'iphone-13-128gb',
            'https://example.com/listing/123?tracking=removed',
            'person@example.com',
            '198.51.100.5',
        );

        $observations = new DoctrinePriceObservationRepository($this->entityManager);
        $observations->save(new PriceObservation(
            'iphone-13-128gb',
            new \DateTimeImmutable('now'),
            95000,
            83000,
            108000,
            'high',
            'Manual review',
            PriceObservation::METHODOLOGY_MANUAL,
        ));

        $tools = $this->tools(true, $leadRepository, $productRequests, $priceTips, $observations);
        $statistics = json_decode($tools->statistics(), true, flags: JSON_THROW_ON_ERROR);
        $leads = json_decode($tools->contactLeads(10), true, flags: JSON_THROW_ON_ERROR);
        $requests = json_decode($tools->productRequests(10), true, flags: JSON_THROW_ON_ERROR);
        $tips = json_decode($tools->priceTips(10), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $statistics['submissions']['contact_leads']['total']);
        self::assertSame(1, $statistics['submissions']['product_requests']['last_7_days']);
        self::assertSame(1, $statistics['submissions']['active_price_tips']['total']);
        self::assertGreaterThan(0, $statistics['market_coverage']['tracked_products']);
        self::assertSame(1, $statistics['market_coverage']['products_with_history']);
        self::assertSame(0, $statistics['traffic']['last_30_days']['page_views']);
        self::assertSame('person@example.com', $leads['items'][0]['email']);
        self::assertSame('PlayStation 5 Slim', $requests['items'][0]['product']);
        self::assertSame('https://example.com/listing/123', $tips['items'][0]['listing_url']);
        self::assertArrayNotHasKey('ip_hash', $leads['items'][0]);
        self::assertArrayNotHasKey('ip_hash', $tips['items'][0]);
    }

    public function testAdminListsRejectUnsafeResultLimits(): void
    {
        $tools = $this->tools(true);
        $data = json_decode($tools->contactLeads(101), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Limit must be between 1 and 100.', $data['error']);
    }

    private function tools(
        bool $authenticated,
        ?LeadRepository $leads = null,
        ?ProductRequestStore $requests = null,
        ?PriceTipRepository $tips = null,
        ?PriceObservationRepository $observations = null,
    ): AdminTools {
        $requestStack = new RequestStack();
        $request = new Request();
        if ($authenticated) {
            $request->headers->set('Authorization', 'Bearer admin-test-token');
        }
        $requestStack->push($request);

        return new AdminTools(
            new AdminAccess($requestStack, 'admin-test-token'),
            $leads ?? new DoctrineLeadRepository($this->entityManager),
            $requests ?? new DoctrineProductRequestStore($this->entityManager, 'secret'),
            $tips ?? new DoctrinePriceTipRepository($this->entityManager, 'secret'),
            new GetMarketStatistics(
                new ProductCatalog(),
                $observations ?? new DoctrinePriceObservationRepository($this->entityManager)
            ),
            new TrafficAnalytics(new DoctrinePageViewRepository($this->entityManager, 90)),
        );
    }
}
