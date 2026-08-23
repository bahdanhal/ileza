<?php

declare(strict_types=1);

namespace App\Market\Domain;

interface ProductRepository
{
    /** @return list<Product> */
    public function all(): array;

    public function get(string $slug): ?Product;

    public function save(Product $product): void;

    public function delete(string $slug): void;

    public function count(): int;
}
