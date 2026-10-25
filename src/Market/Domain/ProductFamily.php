<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class ProductFamily
{
    /** @param list<Product> $configurations */
    public function __construct(
        public string $slug,
        public string $name,
        public string $category,
        public string $image,
        public string $imageCredit,
        public string $imageSource,
        public array $configurations,
        public string $imageLicense = 'CC BY-SA 4.0',
        public string $imageLicenseUrl = 'https://creativecommons.org/licenses/by-sa/4.0/',
    ) {
        if ($configurations === []) {
            throw new \InvalidArgumentException('A product family needs at least one configuration.');
        }
    }

    public function defaultConfiguration(): Product
    {
        return $this->configurations[0];
    }
}
