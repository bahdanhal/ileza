<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceAlertRepository;
use App\Market\Domain\PriceObservationRepository;

final readonly class CheckPriceAlerts
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
        private PriceAlertRepository $priceAlerts,
        private PriceAlertMailerInterface $mailer,
    ) {
    }

    /**
     * @return array{
     *     checked_products: int,
     *     active_subscribers: int,
     *     notifications_sent: int,
     *     details: list<array{
     *         product: string,
     *         email: string,
     *         current_price_grosz: int,
     *         target_price_grosz: ?int,
     *         notified: bool
     *     }>
     * }
     */
    public function execute(?string $productSlug = null, bool $dryRun = false): array
    {
        $productsToCheck = [];
        if ($productSlug !== null && $productSlug !== '') {
            $product = $this->catalog->get($productSlug);
            if ($product !== null) {
                $productsToCheck[] = $product;
            }
        } else {
            $productsToCheck = $this->catalog->all();
        }

        $activeSubscribersCount = 0;
        $notificationsSent = 0;
        $details = [];

        foreach ($productsToCheck as $product) {
            $activeAlerts = $this->priceAlerts->findActiveForProduct($product->slug);
            if (empty($activeAlerts)) {
                continue;
            }

            $activeSubscribersCount += count($activeAlerts);
            $history = $this->observations->history($product->slug);
            if (empty($history)) {
                continue;
            }

            $latest = $history[0];
            $previous = count($history) > 1 ? $history[1] : null;

            foreach ($activeAlerts as $alert) {
                $shouldNotify = $alert->shouldNotifyForPrice($latest->medianGrosz, $previous?->medianGrosz);

                if ($shouldNotify) {
                    if (!$dryRun) {
                        $this->mailer->sendPriceDropNotification($alert, $product, $latest, $previous);
                        if ($alert->id !== null) {
                            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                            $this->priceAlerts->updateNotificationState($alert->id, $now, $latest->medianGrosz);
                        }
                    }
                    $notificationsSent++;
                }

                $details[] = [
                    'product' => $product->slug,
                    'email' => $alert->email,
                    'current_price_grosz' => $latest->medianGrosz,
                    'target_price_grosz' => $alert->targetPriceGrosz,
                    'notified' => $shouldNotify,
                ];
            }
        }

        return [
            'checked_products' => count($productsToCheck),
            'active_subscribers' => $activeSubscribersCount,
            'notifications_sent' => $notificationsSent,
            'details' => $details,
        ];
    }
}
