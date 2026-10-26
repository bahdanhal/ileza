<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;
use App\Market\Domain\ProductRepository;

final class ProductCatalog
{
    /** @var list<Product>|null */
    private ?array $products = null;

    /** @var list<ProductFamily>|null */
    private ?array $productFamilies = null;

    public function __construct(
        private readonly ?ProductRepository $repository = null,
    ) {
    }

    /** @return list<Product> */
    public function all(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        if ($this->repository !== null) {
            $fromDb = $this->repository->all();
            if ($fromDb !== []) {
                return $this->products = $fromDb;
            }
        }

        return $this->products = $this->seedProducts();
    }

    /** @return list<Product> */
    public function seedProducts(): array
    {
        return [...$this->iphones(), ...$this->macBooks(), ...$this->ram(), ...$this->gpus(), ...$this->cars()];
    }

    public function get(string $slug): ?Product
    {
        if ($this->products !== null) {
            foreach ($this->products as $product) {
                if ($product->slug === $slug) {
                    return $product;
                }
            }

            return null;
        }

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
        if ($this->productFamilies !== null) {
            return $this->productFamilies;
        }

        $families = [];
        foreach ($this->all() as $product) {
            $families[$this->familySlug($product)][] = $product;
        }

        return $this->productFamilies = array_map(function (array $products): ProductFamily {
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
            if ($first->category === 'laptops') {
                $name = trim(sprintf(
                    '%s %s',
                    $first->specifications['line'] ?? 'MacBook',
                    $first->specifications['chip'] ?? ''
                ));
            }
            [$image, $credit, $source] = $this->familyImage($familySlug, $first->category, $first);
            [$license, $licenseUrl] = self::imageLicense($image, $source);

            return new ProductFamily(
                $familySlug,
                trim($name),
                $first->category,
                $image,
                $credit,
                $source,
                $products,
                $license,
                $licenseUrl,
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
            'karty-graficzne', 'graphics-cards', 'gpus' => [
                'key' => 'gpus',
                'category' => 'gpus',
                'familyFilter' => null,
                'canonicalSlugPl' => 'karty-graficzne',
                'canonicalSlugEn' => 'graphics-cards',
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
            ['key' => 'gpus', 'category' => 'gpus', 'familyFilter' => null, 'slugPl' => 'karty-graficzne', 'slugEn' => 'graphics-cards'],
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

    /**
     * Bundled photos use CC BY-SA 4.0 unless listed here; see public/images/market/ATTRIBUTION.md.
     *
     * @return array{0: string, 1: string}
     */
    public static function imageLicense(string $image, string $source): array
    {
        return match ($image) {
            '/images/market/iphone-12.jpg' => ['CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0/'],
            '/images/market/macbook-air-13-m3.jpg' => ['CC BY-SA 2.0', 'https://creativecommons.org/licenses/by-sa/2.0/'],
            '/images/market/ram-ddr5-laptop.jpg',
            '/images/market/gpu-geforce-rtx-30.jpg',
            '/images/market/gpu-geforce-rtx-50.jpg' => ['CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/'],
            '/images/market/iphone-17.jpg',
            '/images/market/gpu-intel-arc-a.jpg',
            '/images/market/gpu-intel-arc-b.jpg' => ['CC0 1.0', 'https://creativecommons.org/publicdomain/zero/1.0/'],
            '/images/market/gpu-geforce-gtx-10.jpg' => ['Public domain', $source],
            default => ['CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        };
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
            'gpu-geforce-gtx-10' => [
                '/images/market/gpu-geforce-gtx-10.jpg',
                'Evan-Amos',
                'https://commons.wikimedia.org/wiki/File:NVIDIA-GTX-1070-FoundersEdition-FL.jpg',
            ],
            'gpu-geforce-gtx-16' => [
                '/images/market/gpu-geforce-gtx-16.jpg',
                'Solomon203, edited by Pittigrilli',
                'https://commons.wikimedia.org/wiki/File:ASUS_TUF_Gaming_X3_Geforce_GTX_1660_(cropped,_edited).jpg',
            ],
            'gpu-geforce-rtx-20' => [
                '/images/market/gpu-geforce-rtx-20.jpg',
                'Jacek Halicki',
                'https://commons.wikimedia.org/wiki/File:2023_MSI_GeForce_RTX_2080_Gaming_X_Trio_(1).jpg',
            ],
            'gpu-geforce-rtx-30' => [
                '/images/market/gpu-geforce-rtx-30.jpg',
                'PantheraLeo1359531',
                'https://commons.wikimedia.org/wiki/File:Gigabyte_GeForce_RTX_3090_Eagle_OC_24G,_24576_MiB_GDDR6X_Front_20201114_DSC5880.jpg',
            ],
            'gpu-geforce-rtx-40' => [
                '/images/market/gpu-geforce-rtx-40.jpg',
                'Jacek Halicki',
                'https://commons.wikimedia.org/wiki/File:2023_Gigabyte_GeForce_RTX_4070_Aero_OC_12GB_(1).jpg',
            ],
            'gpu-geforce-rtx-50' => [
                '/images/market/gpu-geforce-rtx-50.jpg',
                'PantheraLeo1359531',
                'https://commons.wikimedia.org/wiki/File:Palit_GeForce_RTX_5090_Gamerock_20250530_HOF4166_RAW-Export.png',
            ],
            'gpu-radeon-rx-500' => [
                '/images/market/gpu-radeon-rx-500.jpg',
                'Verte95',
                'https://commons.wikimedia.org/wiki/File:Sapphire_RX_580_Nitro%2B.jpg',
            ],
            'gpu-radeon-rx-5000' => [
                '/images/market/gpu-radeon-rx-5000.jpg',
                'Sebastiaan Broekhoven (ITEagle Europe)',
                'https://commons.wikimedia.org/wiki/File:ASUS_TUF_Gaming_X3_Radeon_RX_5600_XT_EVO_6GB_GDDR6_-_front_view.jpg',
            ],
            'gpu-radeon-rx-6000' => [
                '/images/market/gpu-radeon-rx-6000.jpg',
                'Vitaly Zdanevich',
                'https://commons.wikimedia.org/wiki/File:Amd-radeon-rx6800xt-from2020year-front.jpg',
            ],
            'gpu-radeon-rx-7000' => [
                '/images/market/gpu-radeon-rx-7000.jpg',
                'Geni',
                'https://commons.wikimedia.org/wiki/File:Sapphire_AMD_Radeon_RX_7900_XTX.jpg',
            ],
            'gpu-radeon-rx-9000' => [
                '/images/market/gpu-radeon-rx-9000.jpg',
                'Chaddy',
                'https://commons.wikimedia.org/wiki/File:Sapphire_Pulse_Radeon_RX_9070_XT_-_L%C3%BCfter_1.jpg',
            ],
            'gpu-intel-arc-a' => [
                '/images/market/gpu-intel-arc-a.jpg',
                'Telaneo',
                'https://commons.wikimedia.org/wiki/File:Intel_Arc_A770_front.jpg',
            ],
            'gpu-intel-arc-b' => [
                '/images/market/gpu-intel-arc-b.jpg',
                'Jiahwang',
                'https://commons.wikimedia.org/wiki/File:Intel_Arc_Battlemage_B580_Limited_Edition.jpg',
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
                'gpus' => [
                    '/images/market/gpu-geforce-rtx-30.jpg',
                    'PantheraLeo1359531',
                    'https://commons.wikimedia.org/wiki/File:Gigabyte_GeForce_RTX_3090_Eagle_OC_24G,_24576_MiB_GDDR6X_Front_20201114_DSC5880.jpg',
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
        if ($product->category !== 'laptops' && $product->familySlug !== null && $product->familySlug !== '') {
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

        $chip = (string) ($product->specifications['chip'] ?? '');

        return strtolower(str_replace(
            ' ',
            '-',
            sprintf('%s-%s', $line, $chip)
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
                        sprintf('Exact %s model and storage configuration. The estimate concerns the product itself and excludes unrelated bundles or accessories.', $name),
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
                        sprintf('Exact Apple %s display, chip, unified-memory, and SSD configuration. The estimate concerns the product itself and excludes unrelated bundles or accessories.', $name),
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
                    sprintf('Exact single %s %s RAM module in the %s form factor, with the specified speed.', $group['type'], $module['capacity'], $group['form_factor']),
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
    private function gpus(): array
    {
        $products = [];

        /**
         * One configuration per chip model and VRAM size, from any board partner: [model, VRAM, class].
         *
         * @var list<array{
         *     family: string,
         *     family_name: string,
         *     brand: string,
         *     line: string,
         *     slug_prefix: string,
         *     generation: string,
         *     configurations: list<array{string, string, string}>
         * }> $groups
         */
        $groups = [
            [
                'family' => 'gpu-geforce-gtx-10',
                'family_name' => 'NVIDIA GeForce GTX 10',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce GTX 10',
                'configurations' => [
                    ['GTX 1050 Ti', '4 GB', 'Entry-level'],
                    ['GTX 1060', '3 GB', 'Mid-range'],
                    ['GTX 1060', '6 GB', 'Mid-range'],
                    ['GTX 1070', '8 GB', 'Upper mid-range'],
                    ['GTX 1070 Ti', '8 GB', 'Upper mid-range'],
                    ['GTX 1080', '8 GB', 'High-end'],
                    ['GTX 1080 Ti', '11 GB', 'High-end'],
                ],
            ],
            [
                'family' => 'gpu-geforce-gtx-16',
                'family_name' => 'NVIDIA GeForce GTX 16',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce GTX 16',
                'configurations' => [
                    ['GTX 1630', '4 GB', 'Entry-level'],
                    ['GTX 1650', '4 GB', 'Entry-level'],
                    ['GTX 1650 Super', '4 GB', 'Entry-level'],
                    ['GTX 1660', '6 GB', 'Mid-range'],
                    ['GTX 1660 Super', '6 GB', 'Mid-range'],
                    ['GTX 1660 Ti', '6 GB', 'Mid-range'],
                ],
            ],
            [
                'family' => 'gpu-geforce-rtx-20',
                'family_name' => 'NVIDIA GeForce RTX 20',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce RTX 20',
                'configurations' => [
                    ['RTX 2060', '6 GB', 'Mid-range'],
                    ['RTX 2060', '12 GB', 'Mid-range'],
                    ['RTX 2060 Super', '8 GB', 'Mid-range'],
                    ['RTX 2070', '8 GB', 'Upper mid-range'],
                    ['RTX 2070 Super', '8 GB', 'Upper mid-range'],
                    ['RTX 2080', '8 GB', 'High-end'],
                    ['RTX 2080 Super', '8 GB', 'High-end'],
                    ['RTX 2080 Ti', '11 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-geforce-rtx-30',
                'family_name' => 'NVIDIA GeForce RTX 30',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce RTX 30',
                'configurations' => [
                    ['RTX 3050', '6 GB', 'Entry-level'],
                    ['RTX 3050', '8 GB', 'Entry-level'],
                    ['RTX 3060', '8 GB', 'Mid-range'],
                    ['RTX 3060', '12 GB', 'Mid-range'],
                    ['RTX 3060 Ti', '8 GB', 'Mid-range'],
                    ['RTX 3070', '8 GB', 'Upper mid-range'],
                    ['RTX 3070 Ti', '8 GB', 'Upper mid-range'],
                    ['RTX 3080', '10 GB', 'High-end'],
                    ['RTX 3080', '12 GB', 'High-end'],
                    ['RTX 3080 Ti', '12 GB', 'High-end'],
                    ['RTX 3090', '24 GB', 'Flagship'],
                    ['RTX 3090 Ti', '24 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-geforce-rtx-40',
                'family_name' => 'NVIDIA GeForce RTX 40',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce RTX 40',
                'configurations' => [
                    ['RTX 4060', '8 GB', 'Mid-range'],
                    ['RTX 4060 Ti', '8 GB', 'Mid-range'],
                    ['RTX 4060 Ti', '16 GB', 'Mid-range'],
                    ['RTX 4070', '12 GB', 'Upper mid-range'],
                    ['RTX 4070 Super', '12 GB', 'Upper mid-range'],
                    ['RTX 4070 Ti', '12 GB', 'Upper mid-range'],
                    ['RTX 4070 Ti Super', '16 GB', 'Upper mid-range'],
                    ['RTX 4080', '16 GB', 'High-end'],
                    ['RTX 4080 Super', '16 GB', 'High-end'],
                    ['RTX 4090', '24 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-geforce-rtx-50',
                'family_name' => 'NVIDIA GeForce RTX 50',
                'brand' => 'NVIDIA',
                'line' => 'NVIDIA GeForce',
                'slug_prefix' => 'gpu-geforce',
                'generation' => 'GeForce RTX 50',
                'configurations' => [
                    ['RTX 5050', '8 GB', 'Entry-level'],
                    ['RTX 5060', '8 GB', 'Mid-range'],
                    ['RTX 5060 Ti', '8 GB', 'Mid-range'],
                    ['RTX 5060 Ti', '16 GB', 'Mid-range'],
                    ['RTX 5070', '12 GB', 'Upper mid-range'],
                    ['RTX 5070 Ti', '16 GB', 'Upper mid-range'],
                    ['RTX 5080', '16 GB', 'High-end'],
                    ['RTX 5090', '32 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-radeon-rx-500',
                'family_name' => 'AMD Radeon RX 500',
                'brand' => 'AMD',
                'line' => 'AMD Radeon',
                'slug_prefix' => 'gpu-radeon',
                'generation' => 'Radeon RX 500',
                'configurations' => [
                    ['RX 550', '4 GB', 'Entry-level'],
                    ['RX 560', '4 GB', 'Entry-level'],
                    ['RX 570', '4 GB', 'Mid-range'],
                    ['RX 570', '8 GB', 'Mid-range'],
                    ['RX 580', '4 GB', 'Mid-range'],
                    ['RX 580', '8 GB', 'Mid-range'],
                    ['RX 590', '8 GB', 'Mid-range'],
                ],
            ],
            [
                'family' => 'gpu-radeon-rx-5000',
                'family_name' => 'AMD Radeon RX 5000',
                'brand' => 'AMD',
                'line' => 'AMD Radeon',
                'slug_prefix' => 'gpu-radeon',
                'generation' => 'Radeon RX 5000',
                'configurations' => [
                    ['RX 5500 XT', '4 GB', 'Entry-level'],
                    ['RX 5500 XT', '8 GB', 'Entry-level'],
                    ['RX 5600 XT', '6 GB', 'Mid-range'],
                    ['RX 5700', '8 GB', 'Upper mid-range'],
                    ['RX 5700 XT', '8 GB', 'Upper mid-range'],
                ],
            ],
            [
                'family' => 'gpu-radeon-rx-6000',
                'family_name' => 'AMD Radeon RX 6000',
                'brand' => 'AMD',
                'line' => 'AMD Radeon',
                'slug_prefix' => 'gpu-radeon',
                'generation' => 'Radeon RX 6000',
                'configurations' => [
                    ['RX 6400', '4 GB', 'Entry-level'],
                    ['RX 6500 XT', '4 GB', 'Entry-level'],
                    ['RX 6600', '8 GB', 'Mid-range'],
                    ['RX 6600 XT', '8 GB', 'Mid-range'],
                    ['RX 6650 XT', '8 GB', 'Mid-range'],
                    ['RX 6700', '10 GB', 'Upper mid-range'],
                    ['RX 6700 XT', '12 GB', 'Upper mid-range'],
                    ['RX 6750 XT', '12 GB', 'Upper mid-range'],
                    ['RX 6800', '16 GB', 'High-end'],
                    ['RX 6800 XT', '16 GB', 'High-end'],
                    ['RX 6900 XT', '16 GB', 'Flagship'],
                    ['RX 6950 XT', '16 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-radeon-rx-7000',
                'family_name' => 'AMD Radeon RX 7000',
                'brand' => 'AMD',
                'line' => 'AMD Radeon',
                'slug_prefix' => 'gpu-radeon',
                'generation' => 'Radeon RX 7000',
                'configurations' => [
                    ['RX 7600', '8 GB', 'Mid-range'],
                    ['RX 7600 XT', '16 GB', 'Mid-range'],
                    ['RX 7700 XT', '12 GB', 'Upper mid-range'],
                    ['RX 7800 XT', '16 GB', 'High-end'],
                    ['RX 7900 GRE', '16 GB', 'High-end'],
                    ['RX 7900 XT', '20 GB', 'Flagship'],
                    ['RX 7900 XTX', '24 GB', 'Flagship'],
                ],
            ],
            [
                'family' => 'gpu-radeon-rx-9000',
                'family_name' => 'AMD Radeon RX 9000',
                'brand' => 'AMD',
                'line' => 'AMD Radeon',
                'slug_prefix' => 'gpu-radeon',
                'generation' => 'Radeon RX 9000',
                'configurations' => [
                    ['RX 9060 XT', '8 GB', 'Mid-range'],
                    ['RX 9060 XT', '16 GB', 'Mid-range'],
                    ['RX 9070', '16 GB', 'Upper mid-range'],
                    ['RX 9070 XT', '16 GB', 'Upper mid-range'],
                ],
            ],
            [
                'family' => 'gpu-intel-arc-a',
                'family_name' => 'Intel Arc A',
                'brand' => 'Intel',
                'line' => 'Intel Arc',
                'slug_prefix' => 'gpu-intel-arc',
                'generation' => 'Arc A',
                'configurations' => [
                    ['A380', '6 GB', 'Entry-level'],
                    ['A580', '8 GB', 'Mid-range'],
                    ['A750', '8 GB', 'Mid-range'],
                    ['A770', '8 GB', 'Mid-range'],
                    ['A770', '16 GB', 'Mid-range'],
                ],
            ],
            [
                'family' => 'gpu-intel-arc-b',
                'family_name' => 'Intel Arc B',
                'brand' => 'Intel',
                'line' => 'Intel Arc',
                'slug_prefix' => 'gpu-intel-arc',
                'generation' => 'Arc B',
                'configurations' => [
                    ['B570', '10 GB', 'Mid-range'],
                    ['B580', '12 GB', 'Mid-range'],
                ],
            ],
        ];

        foreach ($groups as $group) {
            foreach ($group['configurations'] as [$model, $vram, $tier]) {
                $slug = strtolower(sprintf(
                    '%s-%s-%s',
                    $group['slug_prefix'],
                    str_replace(' ', '-', $model),
                    str_replace(' ', '', $vram)
                ));

                $products[] = new Product(
                    $slug,
                    sprintf('%s %s %s', $group['line'], $model, $vram),
                    sprintf(
                        // phpcs:ignore Generic.Files.LineLength
                        'Any used, working %s %s graphics card with %s of VRAM, in any partner design (ASUS, MSI, Gigabyte, Palit, Sapphire and others). The estimate covers this exact chip and memory size, not one particular listing.',
                        $group['line'],
                        $model,
                        $vram
                    ),
                    'gpus',
                    [
                        'brand' => $group['brand'],
                        'generation' => $group['generation'],
                        // The model carries the VRAM so the family card can pick every configuration with one select.
                        'model' => sprintf('%s %s', $model, $vram),
                        'tier' => $tier,
                        'vram' => $vram,
                    ],
                    $group['family'],
                    $group['family_name'],
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
                'Peugeot 206 CC with the 1.6-litre petrol engine. The estimate concerns this exact model and engine variant.',
                'cars',
                ['model' => '206 CC', 'engine' => '1.6 petrol', 'market' => 'Poland'],
            ),
            new Product(
                'peugeot-206-cc-2-0-petrol',
                'Peugeot 206 CC 2.0 petrol',
                // phpcs:ignore Generic.Files.LineLength
                'Peugeot 206 CC with the 2.0-litre petrol engine. The estimate concerns this exact model and engine variant.',
                'cars',
                ['model' => '206 CC', 'engine' => '2.0 petrol', 'market' => 'Poland'],
            ),
        ];
    }
}
