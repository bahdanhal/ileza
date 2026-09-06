<?php

declare(strict_types=1);

namespace App\Market\Presentation\Http;

use App\Market\Application\BrowseCatalog;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordProductRequest;
use App\Market\Application\SubmitCommunityPriceTip;
use App\Market\Application\SubscribePriceAlert;
use App\Market\Application\UnsubscribePriceAlert;
use App\Market\Application\VerifyPriceAlert;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MarketController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly GetProductPriceHistory $priceHistory,
        private readonly RecordProductRequest $recordProductRequest,
        private readonly RateLimiterFactory $productRequestLimiter,
        private readonly SubmitCommunityPriceTip $submitPriceTip,
        private readonly RateLimiterFactory $priceTipLimiter,
        private readonly SubscribePriceAlert $subscribePriceAlert,
        private readonly VerifyPriceAlert $verifyPriceAlert,
        private readonly UnsubscribePriceAlert $unsubscribePriceAlert,
        private readonly RateLimiterFactory $priceAlertLimiter,
        private readonly TranslatorInterface $translator,
        private readonly ?BrowseCatalog $browseCatalog = null,
    ) {
    }

    #[Route(
        path: ['pl' => '/', 'en' => '/en/'],
        name: 'market_home',
        methods: ['GET']
    )]
    public function home(?Request $request = null): Response
    {
        $locale = $request?->getLocale() ?? 'pl';
        $catalogFamilies = $this->catalog->families();
        $this->priceHistory->preload($this->productSlugs($catalogFamilies));

        /** @var list<array{
         *     family: ProductFamily,
         *     configurations: list<array{product: Product, latest: ?PriceObservation}>,
         *     initial: array{product: Product, latest: ?PriceObservation},
         *     spec_fields: list<array{key: string, values: list<string>}>,
         *     config_json: string
         * }> $families */
        $families = array_map(function (ProductFamily $family) use ($locale): array {
            $configurations = array_map(fn (Product $product): array => [
                'product' => $product,
                'latest' => $this->priceHistory->latestForProduct($product->slug),
            ], $family->configurations);
            usort($configurations, self::compareConfigurationsByPrice(...));

            $initial = $configurations[0];

            $specKeys = [];
            foreach ($configurations as $cfg) {
                foreach (array_keys($cfg['product']->specifications) as $key) {
                    if (!in_array($key, $specKeys, true)) {
                        $specKeys[] = $key;
                    }
                }
            }

            $specFields = [];
            foreach ($specKeys as $key) {
                $values = [];
                foreach ($configurations as $cfg) {
                    $val = $cfg['product']->specifications[$key] ?? null;
                    if ($val !== null && !in_array((string) $val, $values, true)) {
                        $values[] = (string) $val;
                    }
                }
                if (count($values) > 1) {
                    $specFields[] = [
                        'key' => $key,
                        'values' => $values,
                    ];
                }
            }

            $configData = [];
            if (count($configurations) > 1) {
                foreach ($configurations as $cfg) {
                    $product = $cfg['product'];
                    $latest = $cfg['latest'];
                    $price = $latest && $latest->availability === 'available'
                        ? self::formatSmartMoney($latest->medianGrosz, $locale)
                        : $this->translator->trans('market.unavailable_short', locale: $locale);
                    $note = $latest
                        ? sprintf(
                            '%s %s',
                            $this->translator->trans('market.observed', locale: $locale),
                            $latest->observedAt->format($locale === 'pl' ? 'd.m.Y' : 'M j, Y')
                        )
                        : $this->translator->trans('market.awaiting_note', locale: $locale);

                    $configData[] = [
                        'url' => $locale === 'en' ? '/prices/' . $product->slug : '/ceny/' . $product->slug,
                        'price' => $price,
                        'note' => $note,
                        'specs' => $product->specifications,
                    ];
                }
            }

            return [
                'family' => $family,
                'configurations' => $configurations,
                'initial' => $initial,
                'spec_fields' => $specFields,
                'config_json' => $configData !== [] ? (json_encode($configData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : '',
            ];
        }, $catalogFamilies);
        usort($families, self::compareFamiliesByPrice(...));

        $topDrops = $this->priceHistory->marketMovers(limit: 4, dropsOnly: true);

        $searchProducts = [];
        foreach ($families as $item) {
            $family = $item['family'];
            foreach ($item['configurations'] as $cfg) {
                $product = $cfg['product'];
                $latest = $cfg['latest'];
                $price = $latest && $latest->availability === 'available'
                    ? self::formatSmartMoney($latest->medianGrosz, $locale)
                    : $this->translator->trans('market.unavailable_short', locale: $locale);

                $specTexts = array_values(array_map('strval', $product->specifications));
                $searchText = strtolower($product->name . ' ' . implode(' ', $specTexts) . ' ' . $product->category . ' ' . $family->name);

                $searchProducts[] = [
                    'slug' => $product->slug,
                    'name' => self::localizeLabel($product->name, $locale),
                    'category' => $product->category,
                    'category_label' => $this->translator->trans('market.category.' . $product->category, locale: $locale),
                    'price' => $price,
                    'url' => $locale === 'en' ? '/prices/' . $product->slug : '/ceny/' . $product->slug,
                    'search_text' => $searchText,
                ];
            }
        }
        $searchJson = json_encode($searchProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

        $response = $this->render('market/home.html.twig', [
            'families' => $families,
            'top_drops' => $topDrops,
            'hubs' => $this->catalog->hubs(),
            'search_json' => $searchJson,
        ]);

        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->headers->addCacheControlDirective('stale-while-revalidate', '600');

        return $response;
    }

    public static function formatSmartMoney(int $grosz, string $locale): string
    {
        $pln = $grosz / 100;
        $unit = $locale === 'pl' ? 'zł' : 'PLN';
        if ($pln <= 0) {
            return '0 ' . $unit;
        }
        if ($pln < 100) {
            $rounded = round($pln / 5) * 5;
        } elseif ($pln < 2000) {
            $rounded = round($pln / 10) * 10;
        } elseif ($pln <= 20000) {
            $rounded = round($pln / 100) * 100;
        } else {
            $rounded = round($pln / 500) * 500;
        }

        $thousandsSep = $locale === 'pl' ? ' ' : ',';
        $decPoint = $locale === 'pl' ? ',' : '.';

        return number_format($rounded, 0, $decPoint, $thousandsSep) . ' ' . $unit;
    }

    public static function localizeLabel(string $label, string $locale): string
    {
        if ($locale === 'pl') {
            return str_replace(['-inch', ' petrol'], ['″', ' benzyna'], $label);
        }

        return $label;
    }

    /**
     * @param array{product: Product, latest: ?PriceObservation} $left
     * @param array{product: Product, latest: ?PriceObservation} $right
     */
    private static function compareConfigurationsByPrice(array $left, array $right): int
    {
        $rightPrice = $right['latest']?->availability === 'available' ? $right['latest']->medianGrosz : -1;
        $leftPrice = $left['latest']?->availability === 'available' ? $left['latest']->medianGrosz : -1;
        $priceComparison = $rightPrice <=> $leftPrice;

        return $priceComparison !== 0
            ? $priceComparison
            : strcmp($left['product']->name, $right['product']->name);
    }

    /**
     * @param array{family: ProductFamily, configurations: list<array{product: Product, latest: ?PriceObservation}>} $left
     * @param array{family: ProductFamily, configurations: list<array{product: Product, latest: ?PriceObservation}>} $right
     */
    private static function compareFamiliesByPrice(array $left, array $right): int
    {
        $leftLatest = $left['configurations'][0]['latest'];
        $rightLatest = $right['configurations'][0]['latest'];
        $leftPrice = $leftLatest?->availability === 'available' ? $leftLatest->medianGrosz : -1;
        $rightPrice = $rightLatest?->availability === 'available' ? $rightLatest->medianGrosz : -1;
        $priceComparison = $rightPrice <=> $leftPrice;

        return $priceComparison !== 0
            ? $priceComparison
            : strcmp($left['family']->name, $right['family']->name);
    }

    /**
     * @param list<ProductFamily> $families
     * @return list<string>
     */
    private function productSlugs(array $families): array
    {
        $slugs = [];
        foreach ($families as $family) {
            foreach ($family->configurations as $product) {
                $slugs[] = $product->slug;
            }
        }

        return $slugs;
    }

    #[Route(
        path: [
            'en' => '/prices/{category}',
            'pl' => '/ceny/{category}',
        ],
        name: 'market_hub',
        requirements: ['category' => 'laptops|macbook|smartphones|iphone|ram|cars|samochody|laptopy|smartfony'],
        methods: ['GET'],
        priority: 20
    )]
    public function hub(string $category, ?Request $request = null): Response
    {
        $hubInfo = $this->catalog->resolveHub($category);
        if ($hubInfo === null) {
            throw $this->createNotFoundException();
        }

        $categoryKey = $hubInfo['category'];
        $familyFilter = $hubInfo['familyFilter'];
        $hubKey = $hubInfo['key'];
        $view = ($request?->query->get('view') === 'matrix') ? 'matrix' : 'cards';

        $catalogFamilies = $this->catalog->familiesForHub($categoryKey, $familyFilter);
        $this->priceHistory->preload($this->productSlugs($catalogFamilies));

        $stats = $this->priceHistory->categoryStatistics($categoryKey, $familyFilter);
        $matrix = $this->priceHistory->categoryMatrix($categoryKey, $familyFilter);
        $movers = $this->priceHistory->marketMovers($categoryKey, $familyFilter, limit: 4, dropsOnly: true);

        $browse = ($this->browseCatalog ?? new BrowseCatalog())->execute($matrix, $request?->query->all() ?? []);

        /** @var array<string, array{family: ProductFamily, configurations: list<array{product: Product, latest: ?PriceObservation}>}> $familiesBySlug */
        $familiesBySlug = [];
        foreach ($browse['rows'] as $row) {
            if (!$row['family'] instanceof ProductFamily) {
                continue;
            }
            $familiesBySlug[$row['family']->slug] ??= [
                'family' => $row['family'],
                'configurations' => [],
            ];
            $familiesBySlug[$row['family']->slug]['configurations'][] = [
                'product' => $row['product'],
                'latest' => $row['latest'],
            ];
        }
        $families = array_values($familiesBySlug);
        foreach ($families as &$family) {
            usort($family['configurations'], self::compareConfigurationsByPrice(...));
        }
        unset($family);
        usort($families, self::compareFamiliesByPrice(...));

        return $this->render('market/hub.html.twig', [
            'hub' => $hubInfo,
            'hub_key' => $hubKey,
            'category_key' => $categoryKey,
            'stats' => $stats,
            'matrix' => $browse['rows'],
            'movers' => $movers,
            'families' => $families,
            'hubs' => $this->catalog->hubs(),
            'browse' => $browse,
            'view' => $view,
        ]);
    }

    #[Route(path: '/pl/', name: 'legacy_polish_market_home', methods: ['GET'])]
    public function legacyPolishHome(): Response
    {
        return $this->redirectToRoute('market_home', ['_locale' => 'pl'], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych'],
        name: 'legacy_market_home',
        methods: ['GET']
    )]
    public function legacyHome(Request $request): Response
    {
        return $this->redirectToRoute('market_home', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/prices/{slug}', 'pl' => '/ceny/{slug}'],
        name: 'market_product',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function product(string $slug): Response
    {
        $detailed = $this->priceHistory->detailedHistory($slug);
        if ($detailed === null) {
            throw $this->createNotFoundException();
        }

        $hubInfo = $this->catalog->resolveHub($detailed['product']->category);

        return $this->render('market/product.html.twig', [
            'product' => $detailed['product'],
            'family' => $detailed['family'],
            'history' => $detailed['history'],
            'latest' => $detailed['latest'],
            'one_month_ago' => $detailed['one_month_ago'],
            'hub_info' => $hubInfo,
            'hubs' => $this->catalog->hubs(),
        ]);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}'],
        name: 'legacy_market_product',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function legacyProduct(string $slug, Request $request): Response
    {
        return $this->redirectToRoute('market_product', [
            '_locale' => $request->getLocale(),
            'slug' => $slug,
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/request', 'pl' => '/zglos'],
        name: 'market_request',
        methods: ['POST'],
        priority: 10
    )]
    #[Route(
        path: ['en' => '/tools/poland-used-price-index/request', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/zglos'],
        name: 'legacy_market_request',
        methods: ['POST'],
        priority: 5
    )]
    public function requestProduct(Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('market.request.saved')]);
        }
        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        $limit = $this->productRequestLimiter->create($limitKey)->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('market.request.too_many')], 429);
        }
        $product = trim((string) $request->request->get('product'));
        $email = strtolower(trim((string) $request->request->get('email')));
        if ($product === '' || mb_strlen($product) > 160 || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 422);
        }
        try {
            $this->recordProductRequest->execute($product, $email, $request->getClientIp() ?? 'unknown');
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 422);
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('market.request.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.request.saved')]);
    }

    #[Route(
        path: ['en' => '/prices/{slug}/price-tip', 'pl' => '/ceny/{slug}/okazja'],
        name: 'market_price_tip',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 20,
    )]
    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}/price-tip', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}/okazja'],
        name: 'legacy_market_price_tip',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 15,
    )]
    public function submitPriceTip(string $slug, Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('market.tip.saved')]);
        }
        if ($this->catalog->get($slug) === null) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 404);
        }
        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        if (!$this->priceTipLimiter->create($limitKey)->consume()->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('market.tip.too_many')], 429);
        }

        try {
            $this->submitPriceTip->execute(
                $slug,
                (string) $request->request->get('listing_url'),
                (string) $request->request->get('email'),
                $request->getClientIp() ?? 'unknown',
            );
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 422);
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('market.tip.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.tip.saved')]);
    }

    #[Route(
        path: ['en' => '/prices/{slug}/alert', 'pl' => '/ceny/{slug}/alert'],
        name: 'market_price_alert',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 20,
    )]
    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}/alert', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}/alert'],
        name: 'legacy_market_price_alert',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 15,
    )]
    public function subscribeAlert(string $slug, Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('market.alert.invalid')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('market.alert.saved')]);
        }
        if ($this->catalog->get($slug) === null) {
            return $this->json(['error' => $this->translator->trans('market.alert.invalid')], 404);
        }
        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        if (!$this->priceAlertLimiter->create($limitKey)->consume()->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('market.alert.too_many')], 429);
        }

        $email = trim((string) $request->request->get('email'));
        $targetPriceRaw = trim((string) $request->request->get('target_price', ''));
        $targetPriceGrosz = null;

        if ($targetPriceRaw !== '' && is_numeric($targetPriceRaw)) {
            $targetPricePln = (float) $targetPriceRaw;
            if ($targetPricePln > 0) {
                $targetPriceGrosz = (int) round($targetPricePln * 100);
            }
        }

        try {
            $this->subscribePriceAlert->execute(
                $slug,
                $email,
                $targetPriceGrosz,
                $request->getClientIp() ?? 'unknown',
                $request->getLocale(),
            );
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => $this->translator->trans('market.alert.invalid')], 422);
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('market.alert.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.alert.saved')]);
    }

    #[Route(
        path: ['en' => '/prices/alert/verify/{token}', 'pl' => '/ceny/alert/potwierdz/{token}'],
        name: 'market_price_alert_verify',
        requirements: ['token' => '[a-f0-9]{32,64}'],
        methods: ['GET'],
        priority: 25,
    )]
    public function verifyAlert(string $token): Response
    {
        $alert = $this->verifyPriceAlert->execute($token);
        if ($alert === null) {
            throw $this->createNotFoundException('Verification token is invalid or has expired.');
        }

        $product = $this->catalog->get($alert->productSlug);

        return $this->render('market/alert_verified.html.twig', [
            'alert' => $alert,
            'product' => $product,
        ]);
    }

    #[Route(
        path: ['en' => '/prices/alert/unsubscribe/{token}', 'pl' => '/ceny/alert/rezygnuj/{token}'],
        name: 'market_price_alert_unsubscribe',
        requirements: ['token' => '[a-f0-9]{32,64}'],
        methods: ['GET'],
        priority: 25,
    )]
    public function unsubscribeAlert(string $token): Response
    {
        $alert = $this->unsubscribePriceAlert->execute($token);
        if ($alert === null) {
            throw $this->createNotFoundException('Unsubscribe token is invalid or has expired.');
        }

        $product = $this->catalog->get($alert->productSlug);

        return $this->render('market/alert_unsubscribed.html.twig', [
            'alert' => $alert,
            'product' => $product,
        ]);
    }
}
