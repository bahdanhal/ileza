<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Analytics\Application\TrafficAnalytics;
use App\Market\Application\DeletePriceObservation;
use App\Market\Application\GetMarketStatistics;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\Product;
use App\Market\Domain\ProductRepository;
use App\Market\Domain\ProductRequestStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketAdminController extends AbstractController
{
    private const AUTH_COOKIE_NAME = 'market_admin_auth';

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly ProductRepository $productRepository,
        private readonly GetMarketStatistics $marketStatistics,
        private readonly RecordPriceObservation $recordObservation,
        private readonly DeletePriceObservation $deleteObservation,
        private readonly ProductRequestStore $productRequests,
        private readonly PriceTipRepository $priceTips,
        private readonly PriceAlertRepository $priceAlerts,
        private readonly TrafficAnalytics $trafficAnalytics,
        private readonly string $secret,
        private readonly string $projectDir = '',
    ) {
    }

    #[Route('/admin', name: 'market_admin_dashboard_root', methods: ['GET'])]
    #[Route('/admin/market', name: 'market_admin_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->render('admin/login.html.twig');
        }

        $category = $request->query->get('category');
        $search = strtolower(trim((string) $request->query->get('q', '')));

        $stats = $this->marketStatistics->calculate();
        $allProducts = $stats['all_products'];
        $allProductData = $stats['products'];

        $productData = array_values(array_filter($allProductData, function (array $item) use ($category, $search): bool {
            /** @var Product $product */
            $product = $item['product'];
            if ($category !== null && $category !== '' && $product->category !== $category) {
                return false;
            }
            if ($search !== '' && !str_contains(strtolower($product->name), $search) && !str_contains($product->slug, $search)) {
                return false;
            }
            return true;
        }));

        $requests = $this->productRequests->all();
        $priceTips = $this->priceTips->all();
        $priceAlerts = $this->priceAlerts->all();
        $alertStats = $this->priceAlerts->countStatistics();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');
        $traffic = $this->trafficAnalytics->summary($now);
        $trafficPeak = max(1, ...array_column($traffic['daily'], 'page_views'));
        $traffic['daily'] = array_map(static fn (array $day): array => [
            ...$day,
            'height_percent' => $day['page_views'] === 0
                ? 2
                : max(8, (int) round(($day['page_views'] / $trafficPeak) * 100)),
        ], $traffic['daily']);

        return $this->render('admin/market.html.twig', [
            'products' => $productData,
            'all_products' => $allProducts,
            'families' => $this->catalog->families(),
            'requests' => $requests,
            'price_tips' => $priceTips,
            'price_alerts' => $priceAlerts,
            'alert_stats' => $alertStats,
            'traffic' => $traffic,
            'statistics' => [
                'requests_last_7_days' => count(array_filter(
                    $requests,
                    static fn (array $item): bool => new \DateTimeImmutable($item['timestamp']) >= $sevenDaysAgo,
                )),
                'price_tips_last_7_days' => count(array_filter(
                    $priceTips,
                    static fn (PriceTip $tip): bool => $tip->submittedAt >= $sevenDaysAgo,
                )),
                'tracked_products' => $stats['tracked_products'],
                'catalog_coverage_percent' => $stats['catalog_coverage_percent'],
                'observation_points' => $stats['observation_points'],
                'stale_products' => count($stats['stale_products']),
                'active_alerts' => $alertStats['active'],
                'total_alerts' => $alertStats['total'],
            ],
            'current_category' => $category,
            'search' => $search,
            'status' => $request->query->get('status'),
            'error' => $request->query->get('error'),
        ]);
    }

    #[Route('/admin/login', name: 'market_admin_login_root', methods: ['POST'])]
    #[Route('/admin/market/login', name: 'market_admin_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $password = (string) $request->request->get('password', '');
        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret));

        if (
            hash_equals($adminToken, $password)
            || hash_equals($this->secret, $password)
            || (isset($_ENV['MARKET_ADMIN_TOKEN']) && hash_equals((string) $_ENV['MARKET_ADMIN_TOKEN'], $password))
        ) {
            $response = $this->redirectToRoute('market_admin_dashboard');
            $authHash = hash_hmac('sha256', 'market_admin_authenticated', $this->secret);
            $response->headers->setCookie(
                Cookie::create(
                    self::AUTH_COOKIE_NAME,
                    $authHash,
                    time() + (86400 * 30),
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );

            return $response;
        }

        return $this->render('admin/login.html.twig', [
            'error' => 'Invalid token or passphrase.',
        ]);
    }

    #[Route('/admin/market/logout', name: 'market_admin_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $response = $this->redirectToRoute('market_admin_dashboard');
        $response->headers->clearCookie(self::AUTH_COOKIE_NAME, '/');

        return $response;
    }

    #[Route('/admin/market/observation', name: 'market_admin_save_observation', methods: ['POST'])]
    public function saveObservation(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->request->get('product_slug'));
        $dateStr = trim((string) $request->request->get('observed_at'));
        $medianPln = (float) $request->request->get('median_pln');
        $lowPln = (float) $request->request->get('low_pln');
        $highPln = (float) $request->request->get('high_pln');
        $sampleSize = (int) $request->request->get('sample_size', 5);
        $confidence = (string) $request->request->get('confidence', 'high');

        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Unknown product slug: ' . $slug]);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Invalid date format. Expected YYYY-MM-DD.']);
        }

        try {
            $observedAt = new \DateTimeImmutable($dateStr . ' 12:00:00', new \DateTimeZone('Europe/Warsaw'));
            $this->recordObservation->execute(
                $slug,
                $observedAt,
                (int) round($medianPln * 100),
                (int) round($lowPln * 100),
                (int) round($highPln * 100),
                max(3, $sampleSize),
                $confidence,
                '',
                PriceObservation::METHODOLOGY_MANUAL
            );
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to save observation: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Observation saved for ' . $product->name]);
    }

    #[Route('/admin/market/delete-observation', name: 'market_admin_delete_observation', methods: ['POST'])]
    public function deleteObservation(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->request->get('product_slug'));
        $date = trim((string) $request->request->get('date'));

        try {
            $this->deleteObservation->execute($slug, $date);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to delete: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Deleted observation for ' . $slug . ' (' . $date . ')']);
    }

    #[Route('/admin/market/product/save', name: 'market_admin_save_product', methods: ['POST'])]
    public function saveProduct(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = strtolower(trim((string) $request->request->get('slug')));
        $name = trim((string) $request->request->get('name'));
        $category = trim((string) $request->request->get('category'));
        $definition = trim((string) $request->request->get('definition'));
        $familySlug = trim((string) $request->request->get('family_slug'));
        $familyName = trim((string) $request->request->get('family_name'));
        $imageUrl = trim((string) $request->request->get('image_url'));
        $imageCredit = trim((string) $request->request->get('image_credit'));
        $imageSource = trim((string) $request->request->get('image_source'));
        $specsInput = trim((string) $request->request->get('specifications', '{}'));

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Product slug must be lowercase alphanumeric with hyphens.']);
        }

        if ($name === '' || $category === '' || $definition === '') {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Name, category, and definition are required.']);
        }

        /** @var array<string, mixed> $specifications */
        $specifications = [];
        if ($specsInput !== '') {
            try {
                $decoded = json_decode($specsInput, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $specifications = $decoded;
                }
            } catch (\JsonException) {
                return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Specifications must be valid JSON format.']);
            }
        }

        $existing = $this->productRepository->get($slug);
        $finalImage = $imageUrl !== '' ? $imageUrl : $existing?->image;
        $finalCredit = $imageCredit !== '' ? $imageCredit : $existing?->imageCredit;
        $finalSource = $imageSource !== '' ? $imageSource : $existing?->imageSource;

        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image_file');
        if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            $mime = (string) $imageFile->getMimeType();
            if (!in_array($mime, $allowedMimes, true)) {
                return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Invalid image format. Allowed: JPG, PNG, WEBP, SVG.']);
            }

            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'svg',
            };
            $filename = $slug . '-' . time() . '.' . $ext;
            $targetDir = ($this->projectDir !== '' ? $this->projectDir : sys_get_temp_dir()) . '/public/images/market';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $imageFile->move($targetDir, $filename);
            $finalImage = '/images/market/' . $filename;
        }

        $product = new Product(
            $slug,
            $name,
            $definition,
            $category,
            $specifications,
            $familySlug !== '' ? $familySlug : $slug,
            $familyName !== '' ? $familyName : $name,
            $finalImage,
            $finalCredit,
            $finalSource,
        );

        try {
            $this->productRepository->save($product);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to save product: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Product saved: ' . $product->name]);
    }

    #[Route('/admin/market/product/delete', name: 'market_admin_delete_product', methods: ['POST'])]
    public function deleteProduct(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->request->get('slug'));
        if ($slug === '') {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Product slug is required.']);
        }

        try {
            $this->productRepository->delete($slug);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to delete product: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Deleted product: ' . $slug]);
    }

    private function isAuthenticated(Request $request): bool
    {
        $token = $request->headers->get('X-Admin-Token');
        if ($token === null || $token === '') {
            $authHeader = (string) $request->headers->get('Authorization', '');
            if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret));

        if (
            $token !== null
            && $token !== ''
            && (hash_equals($adminToken, $token)
                || hash_equals($this->secret, $token)
                || (isset($_ENV['MARKET_ADMIN_TOKEN']) && hash_equals((string) $_ENV['MARKET_ADMIN_TOKEN'], $token)))
        ) {
            return true;
        }

        $cookie = $request->cookies->get(self::AUTH_COOKIE_NAME);
        if ($cookie !== null) {
            $expected = hash_hmac('sha256', 'market_admin_authenticated', $this->secret);
            return hash_equals($expected, $cookie);
        }

        return false;
    }
}
