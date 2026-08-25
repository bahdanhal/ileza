<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;

final readonly class UnsubscribePriceAlert
{
    public function __construct(
        private PriceAlertRepository $priceAlerts,
    ) {
    }

    public function execute(string $token): ?PriceAlert
    {
        $cleanToken = trim($token);
        if ($cleanToken === '' || strlen($cleanToken) > 64) {
            return null;
        }

        return $this->priceAlerts->markUnsubscribed($cleanToken);
    }
}
