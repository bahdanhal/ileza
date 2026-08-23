<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Entity\ProductEntity;
use App\Market\Domain\Product;
use App\Market\Domain\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<Product> */
    public function all(): array
    {
        try {
            $repository = $this->entityManager->getRepository(ProductEntity::class);
            /** @var list<ProductEntity> $entities */
            $entities = $repository->findBy([], ['id' => 'ASC']);

            return array_map(static fn (ProductEntity $entity): Product => $entity->toDomain(), $entities);
        } catch (\Throwable) {
            return [];
        }
    }

    public function get(string $slug): ?Product
    {
        try {
            $repository = $this->entityManager->getRepository(ProductEntity::class);
            /** @var ProductEntity|null $entity */
            $entity = $repository->findOneBy(['slug' => $slug]);

            return $entity?->toDomain();
        } catch (\Throwable) {
            return null;
        }
    }

    public function save(Product $product): void
    {
        $repository = $this->entityManager->getRepository(ProductEntity::class);
        /** @var ProductEntity|null $existing */
        $existing = $repository->findOneBy(['slug' => $product->slug]);

        if ($existing !== null) {
            $existing->updateFromProduct($product);
        } else {
            $entity = new ProductEntity(
                $product->slug,
                $product->name,
                $product->definition,
                $product->category,
                $product->familySlug ?? $product->slug,
                $product->familyName ?? $product->name,
                $product->image,
                $product->imageCredit,
                $product->imageSource,
                $product->specifications,
            );
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function delete(string $slug): void
    {
        $repository = $this->entityManager->getRepository(ProductEntity::class);
        /** @var ProductEntity|null $existing */
        $existing = $repository->findOneBy(['slug' => $slug]);

        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }
    }

    public function count(): int
    {
        try {
            $repository = $this->entityManager->getRepository(ProductEntity::class);

            return (int) $repository->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
