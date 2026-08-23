<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\Product;
use App\Market\Infrastructure\DoctrineProductRepository;
use App\Tests\DoctrineTestCase;

final class DoctrineProductRepositoryTest extends DoctrineTestCase
{
    public function testSavesRetrievesUpdatesAndDeletesProducts(): void
    {
        $repository = new DoctrineProductRepository($this->entityManager);

        self::assertSame(0, $repository->count());
        self::assertEmpty($repository->all());
        self::assertNull($repository->get('iphone-16-pro-256gb'));

        $product1 = new Product(
            'iphone-16-pro-256gb',
            'Apple iPhone 16 Pro 256 GB',
            'Used functional iPhone 16 Pro',
            'smartphones',
            ['generation' => 'iPhone 16 Pro', 'storage' => '256 GB'],
            'iphone-16-pro',
            'Apple iPhone 16 Pro',
            '/images/market/iphone-16-pro.jpg',
            'Editorial',
            'https://example.com/source',
        );

        $product2 = new Product(
            'macbook-air-m3-13-16gb-512gb',
            'MacBook Air 13" M3 16 GB 512 GB',
            'Used functional MacBook Air M3',
            'laptops',
            ['line' => 'MacBook Air', 'chip' => 'M3', 'display' => '13-inch', 'ram' => '16 GB', 'storage' => '512 GB'],
            'macbook-air-13-m3',
            'MacBook Air 13" M3',
            '/images/market/macbook-air-m3.jpg',
            'Editorial',
            'https://example.com/source2',
        );

        $repository->save($product1);
        $repository->save($product2);

        self::assertSame(2, $repository->count());
        $all = $repository->all();
        self::assertCount(2, $all);

        $retrieved = $repository->get('iphone-16-pro-256gb');
        self::assertNotNull($retrieved);
        self::assertSame('Apple iPhone 16 Pro 256 GB', $retrieved->name);
        self::assertSame('smartphones', $retrieved->category);
        self::assertSame('iphone-16-pro', $retrieved->familySlug);
        self::assertSame('Apple iPhone 16 Pro', $retrieved->familyName);
        self::assertSame('/images/market/iphone-16-pro.jpg', $retrieved->image);
        self::assertSame('256 GB', $retrieved->specifications['storage']);

        // Update in-place
        $updatedProduct1 = new Product(
            'iphone-16-pro-256gb',
            'Apple iPhone 16 Pro 256 GB (Updated)',
            'Updated condition definition',
            'smartphones',
            ['generation' => 'iPhone 16 Pro', 'storage' => '256 GB', 'battery' => '95%'],
            'iphone-16-pro',
            'Apple iPhone 16 Pro',
            '/images/market/iphone-16-pro-v2.jpg',
        );
        $repository->save($updatedProduct1);

        self::assertSame(2, $repository->count());
        $afterUpdate = $repository->get('iphone-16-pro-256gb');
        self::assertNotNull($afterUpdate);
        self::assertSame('Apple iPhone 16 Pro 256 GB (Updated)', $afterUpdate->name);
        self::assertSame('/images/market/iphone-16-pro-v2.jpg', $afterUpdate->image);
        self::assertSame('95%', $afterUpdate->specifications['battery']);

        // Delete
        $repository->delete('iphone-16-pro-256gb');
        self::assertSame(1, $repository->count());
        self::assertNull($repository->get('iphone-16-pro-256gb'));
    }
}
