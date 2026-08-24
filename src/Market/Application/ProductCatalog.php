<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;
use App\Market\Domain\ProductRepository;

final readonly class ProductCatalog
{
    public function __construct(
        private ?ProductRepository $repository = null,
    ) {
    }

    /** @return list<Product> */
    public function all(): array
    {
        if ($this->repository !== null) {
            $fromDb = $this->repository->all();
            if ($fromDb !== []) {
                return $fromDb;
            }
        }

        return $this->seedProducts();
    }

    /** @return list<Product> */
    public function seedProducts(): array
    {
        return [...$this->iphones(), ...$this->macBooks(), ...$this->ram(), ...$this->cars()];
    }

    public function get(string $slug): ?Product
    {
        if ($this->repository !== null) {
            $fromDb = $this->repository->get($slug);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        foreach ($this->seedProducts() as $product) {
            if ($product->slug === $slug) {
                return $product;
            }
        }

        return null;
    }

    public function familyFor(string $slug): ?ProductFamily
    {
        foreach ($this->families() as $family) {
            foreach ($family->configurations as $product) {
                if ($product->slug === $slug) {
                    return $family;
                }
            }
        }

        return null;
    }

    /** @return list<ProductFamily> */
    public function families(): array
    {
        $families = [];
        foreach ($this->all() as $product) {
            $families[$this->familySlug($product)][] = $product;
        }

        return array_map(function (array $products): ProductFamily {
            $first = $products[0];
            $familySlug = $this->familySlug($first);
            $name = $first->familyName ?? match ($first->category) {
                'smartphones' => 'Apple ' . ($first->specifications['generation'] ?? $first->name),
                'laptops' => sprintf(
                    '%s %s %s',
                    $first->specifications['line'] ?? 'MacBook Air',
                    $first->specifications['display'] ?? '',
                    $first->specifications['chip'] ?? ''
                ),
                'ram' => $first->specifications['family_name'] ?? 'RAM Memory',
                'cars' => 'Peugeot 206 CC',
                default => $first->name,
            };
            [$image, $credit, $source] = $this->familyImage($familySlug, $first->category, $first);

            return new ProductFamily(
                $familySlug,
                trim($name),
                $first->category,
                $image,
                $credit,
                $source,
                $products,
            );
        }, array_values($families));
    }

    /**
     * @return array{
     *     key: string,
     *     category: string,
     *     familyFilter: ?string,
     *     canonicalSlugPl: string,
     *     canonicalSlugEn: string
     * }|null
     */
    public function resolveHub(string $slug): ?array
    {
        $normalized = strtolower(trim($slug));

        return match ($normalized) {
            'laptopy', 'laptops' => [
                'key' => 'laptops',
                'category' => 'laptops',
                'familyFilter' => null,
                'canonicalSlugPl' => 'laptopy',
                'canonicalSlugEn' => 'laptops',
            ],
            'macbook' => [
                'key' => 'macbook',
                'category' => 'laptops',
                'familyFilter' => 'macbook',
                'canonicalSlugPl' => 'macbook',
                'canonicalSlugEn' => 'macbook',
            ],
            'smartfony', 'smartphones' => [
                'key' => 'smartphones',
                'category' => 'smartphones',
                'familyFilter' => null,
                'canonicalSlugPl' => 'smartfony',
                'canonicalSlugEn' => 'smartphones',
            ],
            'iphone' => [
                'key' => 'iphone',
                'category' => 'smartphones',
                'familyFilter' => 'iphone',
                'canonicalSlugPl' => 'iphone',
                'canonicalSlugEn' => 'iphone',
            ],
            'ram' => [
                'key' => 'ram',
                'category' => 'ram',
                'familyFilter' => null,
                'canonicalSlugPl' => 'ram',
                'canonicalSlugEn' => 'ram',
            ],
            'samochody', 'cars' => [
                'key' => 'cars',
                'category' => 'cars',
                'familyFilter' => null,
                'canonicalSlugPl' => 'samochody',
                'canonicalSlugEn' => 'cars',
            ],
            default => null,
        };
    }

    /**
     * @return list<array{
     *     key: string,
     *     category: string,
     *     familyFilter: ?string,
     *     slugPl: string,
     *     slugEn: string
     * }>
     */
    public function hubs(): array
    {
        return [
            ['key' => 'laptops', 'category' => 'laptops', 'familyFilter' => null, 'slugPl' => 'laptopy', 'slugEn' => 'laptops'],
            ['key' => 'macbook', 'category' => 'laptops', 'familyFilter' => 'macbook', 'slugPl' => 'macbook', 'slugEn' => 'macbook'],
            ['key' => 'smartphones', 'category' => 'smartphones', 'familyFilter' => null, 'slugPl' => 'smartfony', 'slugEn' => 'smartphones'],
            ['key' => 'iphone', 'category' => 'smartphones', 'familyFilter' => 'iphone', 'slugPl' => 'iphone', 'slugEn' => 'iphone'],
            ['key' => 'ram', 'category' => 'ram', 'familyFilter' => null, 'slugPl' => 'ram', 'slugEn' => 'ram'],
            ['key' => 'cars', 'category' => 'cars', 'familyFilter' => null, 'slugPl' => 'samochody', 'slugEn' => 'cars'],
        ];
    }

    /**
     * @return list<Product>
     */
    public function productsForHub(string $category, ?string $familyFilter = null): array
    {
        return array_values(array_filter($this->all(), function (Product $product) use ($category, $familyFilter): bool {
            if ($product->category !== $category) {
                return false;
            }
            if ($familyFilter !== null) {
                return str_contains(strtolower($product->slug), strtolower($familyFilter))
                    || str_contains(strtolower($product->name), strtolower($familyFilter));
            }

            return true;
        }));
    }

    /**
     * @return list<ProductFamily>
     */
    public function familiesForHub(string $category, ?string $familyFilter = null): array
    {
        return array_values(array_filter($this->families(), function (ProductFamily $family) use ($category, $familyFilter): bool {
            if ($family->category !== $category) {
                return false;
            }
            if ($familyFilter !== null) {
                return str_contains(strtolower($family->slug), strtolower($familyFilter))
                    || str_contains(strtolower($family->name), strtolower($familyFilter));
            }

            return true;
        }));
    }

    /** @return array{0: string, 1: string, 2: string} */
    public function familyImage(string $familySlug, string $category, ?Product $product = null): array
    {
        if ($product?->image !== null && $product->image !== '') {
            return [
                $product->image,
                $product->imageCredit ?? 'Editorial archive',
                $product->imageSource ?? '',
            ];
        }
        return match ($familySlug) {
            'iphone-x' => [
                '/images/market/iphone-x.jpg',
                'Gregory Varnum',
                'https://commons.wikimedia.org/wiki/File:Apple_iPhone_X_-_front_(8121).jpg',
            ],
            'iphone-xr' => [
                '/images/market/iphone-xr.jpg',
                '茅野ふたば',
                'https://commons.wikimedia.org/wiki/File:IPhone_XR_Coral.jpg',
            ],
            'iphone-xs' => [
                '/images/market/iphone-xs.jpg',
                'Cullen Steber',
                'https://commons.wikimedia.org/wiki/File:IPhone_XS.jpg',
            ],
            'iphone-11' => [
                '/images/market/iphone-11.jpg',
                'Ahmadkurdi44',
                'https://commons.wikimedia.org/wiki/File:IPhone_11_all_color.jpg',
            ],
            'iphone-se-2020' => [
                '/images/market/iphone-se-2020.jpg',
                'Seth Whales',
                'https://commons.wikimedia.org/wiki/File:IPhone_SE_(2020)_Product_Red.jpg',
            ],
            'iphone-12' => [
                '/images/market/iphone-12.jpg',
                'ajay_suresh',
                'https://commons.wikimedia.org/wiki/File:Apple_iPhone_12_Pro_-_Cameras_(50535314721).jpg',
            ],
            'iphone-13' => [
                '/images/market/iphone-13.jpg',
                'Kskhh',
                'https://commons.wikimedia.org/wiki/File:IPhone_13.jpg',
            ],
            'iphone-se-2022' => [
                '/images/market/iphone-se-2022.jpg',
                'Toad40',
                'https://commons.wikimedia.org/wiki/File:IPhone_SE_3rd_Gen.jpg',
            ],
            'iphone-14' => [
                '/images/market/iphone-14-plus.jpg',
                'Kskhh',
                'https://commons.wikimedia.org/wiki/File:IPhone_13_and_iPhone_14_Plus.jpg',
            ],
            'iphone-15' => [
                '/images/market/iphone-15.jpg',
                'メイド理世',
                'https://commons.wikimedia.org/wiki/File:Apple_iPhone_15_Pink_(November_1,_2024).jpg',
            ],
            'iphone-16' => [
                '/images/market/iphone-16.jpg',
                'メイド理世',
                'https://commons.wikimedia.org/wiki/File:Back_view_of_iPhone_16_Ultramarine.jpg',
            ],
            'iphone-17' => [
                '/images/market/iphone-17.jpg',
                'Olgierd Rudak',
                'https://commons.wikimedia.org/wiki/File:IPhone_17_Pro_(2025-12-28).jpg',
            ],
            'macbook-air-13-m1' => [
                '/images/market/macbook-air-m1.png',
                'L',
                'https://commons.wikimedia.org/wiki/File:Macbook_Air_M1_Silver_PNG.png',
            ],
            'macbook-air-13-m2' => [
                '/images/market/macbook-air-m2.jpg',
                'KKPCW (Kyu3)',
                'https://commons.wikimedia.org/wiki/File:M2_Macbook_Air_Midnight_model_-_1.jpg',
            ],
            'macbook-air-15-m2' => [
                '/images/market/macbook-air-15.jpg',
                'KKPCW (Kyu3)',
                'https://commons.wikimedia.org/wiki/File:Macbook_Air_15_inch_-_1.jpg',
            ],
            'macbook-air-13-m3' => [
                '/images/market/macbook-air-13-m3.jpg',
                'Thomas Amberg',
                'https://commons.wikimedia.org/wiki/File:Hardware_PXL_20240701_181416002_(53829190029).jpg',
            ],
            'macbook-air-15-m3' => [
                '/images/market/macbook-air-15.jpg',
                'KKPCW (Kyu3)',
                'https://commons.wikimedia.org/wiki/File:Macbook_Air_15_inch_-_1.jpg',
            ],
            'ram-ddr4-desktop' => [
                '/images/market/ram-ddr4-desktop.jpg',
                'ElooKoN',
                'https://commons.wikimedia.org/wiki/File:RAM_Module_(SDRAM-DDR4).jpg',
            ],
            'ram-ddr5-desktop' => [
                '/images/market/ram-ddr5-desktop.jpg',
                'Jacek Halicki',
                'https://commons.wikimedia.org/wiki/File:2023_Pami%C4%99ci_Corsair_Vengeance_RGB.jpg',
            ],
            'ram-ddr4-laptop' => [
                '/images/market/ram-ddr4-laptop.jpg',
                'D-Kuru',
                'https://commons.wikimedia.org/wiki/File:DDR_4_RAM_SO-DIMM_16GB_by_Micron-top_front_PNr%C2%B00840.jpg',
            ],
            'ram-ddr5-laptop' => [
                '/images/market/ram-ddr5-laptop.jpg',
                '4300streetcar',
                'https://commons.wikimedia.org/wiki/File:SK_Hynix_DDR5_form_factors.jpg',
            ],
            'peugeot-206-cc' => [
                '/images/market/peugeot-206-cc.jpg',
                'Corvettec6r',
                'https://commons.wikimedia.org/wiki/File:Peugeot_206_CC.jpg',
            ],
            default => match ($category) {
                'smartphones' => [
                    '/images/market/iphone-13.jpg',
                    'Kskhh',
                    'https://commons.wikimedia.org/wiki/File:IPhone_13.jpg',
                ],
                'laptops' => str_contains($familySlug, 'macbook-pro')
                    ? (str_contains($familySlug, '16')
                        ? [
                            '/images/market/macbook-pro-16.jpg',
                            'SimonWaldherr',
                            'https://commons.wikimedia.org/wiki/File:Apple_MacBook_Pro_16%22_M2_Max_5.jpg',
                        ]
                        : [
                            '/images/market/macbook-pro-14.jpg',
                            'Kyu3a',
                            'https://commons.wikimedia.org/wiki/File:M3_Macbook_Pro_14_inch_Space_Grey_model_(cropped).jpg',
                        ])
                    : [
                        '/images/market/macbook-air-m1.png',
                        'L',
                        'https://commons.wikimedia.org/wiki/File:Macbook_Air_M1_Silver_PNG.png',
                    ],
                'ram' => [
                    '/images/market/ram-ddr4-desktop.jpg',
                    'ElooKoN',
                    'https://commons.wikimedia.org/wiki/File:RAM_Module_(SDRAM-DDR4).jpg',
                ],
                default => [
                    '/images/market/iphone-13.jpg',
                    'Kskhh',
                    'https://commons.wikimedia.org/wiki/File:IPhone_13.jpg',
                ],
            },
        };
    }

    public function familySlug(Product $product): string
    {
        if ($product->familySlug !== null && $product->familySlug !== '') {
            return $product->familySlug;
        }

        if ($product->category === 'smartphones') {
            return strtolower(str_replace([' ', '(', ')'], ['-', '', ''], (string) ($product->specifications['generation'] ?? '')));
        }

        if ($product->category === 'cars') {
            return 'peugeot-206-cc';
        }

        if ($product->category === 'ram') {
            return strtolower(str_replace([' ', '(', ')'], ['-', '', ''], (string) ($product->specifications['family'] ?? '')));
        }

        $line = $product->specifications['line'] ?? (
            str_contains($product->name, 'MacBook Pro') ? 'MacBook Pro' : 'MacBook Air'
        );

        $display = (string) ($product->specifications['display'] ?? '');
        $chip = (string) ($product->specifications['chip'] ?? '');

        return strtolower(str_replace(
            [' ', '-inch'],
            ['-', ''],
            sprintf('%s-%s-%s', $line, $display, $chip)
        ));
    }

    /** @return list<Product> */
    private function iphones(): array
    {
        $products = [];

        /** @var list<array{gen: string, variants: array<string, list<string>>}> $generations */
        $generations = [
            ['gen' => 'iPhone X', 'variants' => ['' => ['64', '256']]],
            ['gen' => 'iPhone XR', 'variants' => ['' => ['64', '128', '256']]],
            ['gen' => 'iPhone XS', 'variants' => ['' => ['64', '256', '512'], 'max' => ['64', '256', '512']]],
            ['gen' => 'iPhone 11', 'variants' => [
                '' => ['64', '128', '256'],
                'pro' => ['64', '256', '512'],
                'pro-max' => ['64', '256', '512'],
            ]],
            ['gen' => 'iPhone SE 2020', 'variants' => ['' => ['64', '128', '256']]],
            ['gen' => 'iPhone 12', 'variants' => [
                'mini' => ['64', '128', '256'],
                '' => ['64', '128', '256'],
                'pro' => ['128', '256', '512'],
                'pro-max' => ['128', '256', '512'],
            ]],
            ['gen' => 'iPhone 13', 'variants' => [
                'mini' => ['128', '256', '512'],
                '' => ['128', '256', '512'],
                'pro' => ['128', '256', '512', '1tb'],
                'pro-max' => ['128', '256', '512', '1tb'],
            ]],
            ['gen' => 'iPhone SE 2022', 'variants' => ['' => ['64', '128', '256']]],
            ['gen' => 'iPhone 14', 'variants' => [
                '' => ['128', '256', '512'],
                'plus' => ['128', '256', '512'],
                'pro' => ['128', '256', '512', '1tb'],
                'pro-max' => ['128', '256', '512', '1tb'],
            ]],
            ['gen' => 'iPhone 15', 'variants' => [
                '' => ['128', '256', '512'],
                'plus' => ['128', '256', '512'],
                'pro' => ['128', '256', '512', '1tb'],
                'pro-max' => ['256', '512', '1tb'],
            ]],
            ['gen' => 'iPhone 16', 'variants' => [
                '' => ['128', '256', '512'],
                'plus' => ['128', '256', '512'],
                'pro' => ['128', '256', '512', '1tb'],
                'pro-max' => ['256', '512', '1tb'],
            ]],
            ['gen' => 'iPhone 17', 'variants' => [
                '' => ['256', '512'],
                'pro' => ['256', '512', '1tb'],
                'pro-max' => ['256', '512', '1tb', '2tb'],
            ]],
        ];

        foreach ($generations as $group) {
            $genName = $group['gen'];
            $genSlug = strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $genName));

            foreach ($group['variants'] as $variant => $capacities) {
                $variantName = $variant === '' ? '' : ' ' . ucwords(str_replace('-', ' ', $variant));

                foreach ($capacities as $capacity) {
                    $storage = str_ends_with($capacity, 'tb')
                        ? substr($capacity, 0, -2) . ' TB'
                        : $capacity . ' GB';
                    $name = sprintf('Apple %s%s %s', $genName, $variantName, $storage);
                    $variantSlugPart = $variant === '' ? '' : '-' . $variant;
                    $storageSlugPart = str_replace(' ', '', strtolower($storage));
                    $slug = sprintf('%s%s-%s', $genSlug, $variantSlugPart, $storageSlugPart);

                    $products[] = new Product(
                        $slug,
                        $name,
                        // phpcs:ignore Generic.Files.LineLength
                        sprintf('Unlocked %s, used and fully functional, with intact screen. Include comparable Polish marketplace asking prices and exclude new, damaged, parts-only, locked, bundled and refurbished-as-new units.', $name),
                        'smartphones',
                        [
                            'generation' => $genName,
                            'variant' => trim($variantName) ?: 'Standard',
                            'storage' => $storage,
                        ]
                    );
                }
            }
        }

        return $products;
    }

    /** @return list<Product> */
    private function macBooks(): array
    {
        $products = [];

        /** @var list<array{line: string, chip: string, display: string, memory: list<string>, storage: list<string>}> $families */
        $families = [
            // MacBook Air
            [
                'line' => 'MacBook Air',
                'chip' => 'M1',
                'display' => '13-inch',
                'memory' => ['8 GB', '16 GB'],
                'storage' => ['256 GB', '512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Air',
                'chip' => 'M2',
                'display' => '13-inch',
                'memory' => ['8 GB', '16 GB', '24 GB'],
                'storage' => ['256 GB', '512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Air',
                'chip' => 'M2',
                'display' => '15-inch',
                'memory' => ['8 GB', '16 GB', '24 GB'],
                'storage' => ['256 GB', '512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Air',
                'chip' => 'M3',
                'display' => '13-inch',
                'memory' => ['8 GB', '16 GB', '24 GB'],
                'storage' => ['256 GB', '512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Air',
                'chip' => 'M3',
                'display' => '15-inch',
                'memory' => ['8 GB', '16 GB', '24 GB'],
                'storage' => ['256 GB', '512 GB', '1 TB', '2 TB'],
            ],

            // MacBook Pro 14" & 16" M1 Pro/Max
            [
                'line' => 'MacBook Pro',
                'chip' => 'M1 Pro',
                'display' => '14-inch',
                'memory' => ['16 GB', '32 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M1 Max',
                'display' => '14-inch',
                'memory' => ['32 GB', '64 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M1 Pro',
                'display' => '16-inch',
                'memory' => ['16 GB', '32 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M1 Max',
                'display' => '16-inch',
                'memory' => ['32 GB', '64 GB'],
                'storage' => ['1 TB', '2 TB'],
            ],

            // MacBook Pro 14" & 16" M2 Pro/Max
            [
                'line' => 'MacBook Pro',
                'chip' => 'M2 Pro',
                'display' => '14-inch',
                'memory' => ['16 GB', '32 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M2 Max',
                'display' => '14-inch',
                'memory' => ['32 GB', '64 GB'],
                'storage' => ['1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M2 Pro',
                'display' => '16-inch',
                'memory' => ['16 GB', '32 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M2 Max',
                'display' => '16-inch',
                'memory' => ['32 GB', '64 GB'],
                'storage' => ['1 TB', '2 TB'],
            ],

            // MacBook Pro 14" & 16" M3 / M3 Pro / M3 Max
            [
                'line' => 'MacBook Pro',
                'chip' => 'M3',
                'display' => '14-inch',
                'memory' => ['8 GB', '16 GB', '24 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M3 Pro',
                'display' => '14-inch',
                'memory' => ['18 GB', '36 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M3 Max',
                'display' => '14-inch',
                'memory' => ['36 GB', '48 GB', '64 GB'],
                'storage' => ['1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M3 Pro',
                'display' => '16-inch',
                'memory' => ['18 GB', '36 GB'],
                'storage' => ['512 GB', '1 TB', '2 TB'],
            ],
            [
                'line' => 'MacBook Pro',
                'chip' => 'M3 Max',
                'display' => '16-inch',
                'memory' => ['36 GB', '48 GB', '64 GB'],
                'storage' => ['1 TB', '2 TB'],
            ],
        ];

        foreach ($families as $family) {
            foreach ($family['memory'] as $memory) {
                foreach ($family['storage'] as $storage) {
                    $name = sprintf(
                        '%s %s %s · %s RAM · %s SSD',
                        $family['line'],
                        $family['display'],
                        $family['chip'],
                        $memory,
                        $storage
                    );
                    $slug = strtolower(str_replace([' · ', ' ', '-inch'], ['-', '-', ''], $name));

                    $products[] = new Product(
                        $slug,
                        $name,
                        // phpcs:ignore Generic.Files.LineLength
                        sprintf('Used, fully functional Apple %s in Poland with the exact display, chip, unified-memory and SSD configuration shown. Exclude damaged, parts-only, locked, bundled and refurbished-as-new units.', $name),
                        'laptops',
                        [
                            'line' => $family['line'],
                            'display' => $family['display'],
                            'chip' => $family['chip'],
                            'memory' => $memory,
                            'storage' => $storage,
                        ]
                    );
                }
            }
        }

        return $products;
    }

    /** @return list<Product> */
    private function ram(): array
    {
        $products = [];

        /** @var list<array{family: string, family_name: string, type: string, form_factor: string, modules: list<array{capacity: string, speed: string}>}> $groups */
        $groups = [
            [
                'family' => 'ram-ddr4-desktop',
                'family_name' => 'RAM DDR4 Desktop (DIMM)',
                'type' => 'DDR4',
                'form_factor' => 'DIMM (Desktop)',
                'modules' => [
                    ['capacity' => '8 GB', 'speed' => '3200 MHz'],
                    ['capacity' => '16 GB', 'speed' => '3200 MHz'],
                    ['capacity' => '16 GB', 'speed' => '3600 MHz'],
                    ['capacity' => '32 GB', 'speed' => '3600 MHz'],
                ],
            ],
            [
                'family' => 'ram-ddr5-desktop',
                'family_name' => 'RAM DDR5 Desktop (DIMM)',
                'type' => 'DDR5',
                'form_factor' => 'DIMM (Desktop)',
                'modules' => [
                    ['capacity' => '16 GB', 'speed' => '5600 MHz'],
                    ['capacity' => '16 GB', 'speed' => '6000 MHz'],
                    ['capacity' => '32 GB', 'speed' => '6000 MHz'],
                ],
            ],
            [
                'family' => 'ram-ddr4-laptop',
                'family_name' => 'RAM DDR4 Laptop (SO-DIMM)',
                'type' => 'DDR4',
                'form_factor' => 'SO-DIMM (Laptop)',
                'modules' => [
                    ['capacity' => '8 GB', 'speed' => '3200 MHz'],
                    ['capacity' => '16 GB', 'speed' => '3200 MHz'],
                    ['capacity' => '32 GB', 'speed' => '3200 MHz'],
                ],
            ],
            [
                'family' => 'ram-ddr5-laptop',
                'family_name' => 'RAM DDR5 Laptop (SO-DIMM)',
                'type' => 'DDR5',
                'form_factor' => 'SO-DIMM (Laptop)',
                'modules' => [
                    ['capacity' => '8 GB', 'speed' => '4800 MHz'],
                    ['capacity' => '16 GB', 'speed' => '4800 MHz'],
                    ['capacity' => '16 GB', 'speed' => '5600 MHz'],
                    ['capacity' => '32 GB', 'speed' => '5600 MHz'],
                ],
            ],
        ];

        foreach ($groups as $group) {
            foreach ($group['modules'] as $module) {
                $name = sprintf('RAM %s %s %s', $group['type'], $module['capacity'], $module['speed']);
                $slug = strtolower(str_replace(
                    [' ', '(', ')', '·'],
                    ['-', '', '', ''],
                    sprintf('%s-%s-%s', $group['family'], $module['capacity'], $module['speed'])
                ));

                $products[] = new Product(
                    $slug,
                    $name,
                    // phpcs:ignore Generic.Files.LineLength
                    sprintf('Used, fully functional single %s %s RAM module (%s) in Poland. Exclude defective, ECC server memory, and new-in-box dealer listings.', $group['type'], $module['capacity'], $group['form_factor']),
                    'ram',
                    [
                        'family' => $group['family'],
                        'family_name' => $group['family_name'],
                        'type' => $group['type'],
                        'form_factor' => $group['form_factor'],
                        'capacity' => $module['capacity'],
                        'speed' => $module['speed'],
                    ]
                );
            }
        }

        return $products;
    }

    /** @return list<Product> */
    private function cars(): array
    {
        return [
            new Product(
                'peugeot-206-cc-1-6-petrol',
                'Peugeot 206 CC 1.6 petrol',
                // phpcs:ignore Generic.Files.LineLength
                'Used, registered and roadworthy Peugeot 206 CC with the 1.6-litre petrol engine in Poland. Include complete running cars with normal age-related wear. Exclude damaged, parts-only, non-running, heavily modified, imported-unregistered and dealer-new vehicles.',
                'cars',
                ['model' => '206 CC', 'engine' => '1.6 petrol', 'market' => 'Poland'],
            ),
            new Product(
                'peugeot-206-cc-2-0-petrol',
                'Peugeot 206 CC 2.0 petrol',
                // phpcs:ignore Generic.Files.LineLength
                'Used, registered and roadworthy Peugeot 206 CC with the 2.0-litre petrol engine in Poland. Include complete running cars with normal age-related wear. Exclude damaged, parts-only, non-running, heavily modified, imported-unregistered and dealer-new vehicles.',
                'cars',
                ['model' => '206 CC', 'engine' => '2.0 petrol', 'market' => 'Poland'],
            ),
        ];
    }
}
