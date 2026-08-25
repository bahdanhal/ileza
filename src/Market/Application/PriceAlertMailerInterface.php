<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;

interface PriceAlertMailerInterface
{
    public function sendVerificationEmail(PriceAlert $alert, Product $product): bool;

    public function sendPriceDropNotification(
        PriceAlert $alert,
        Product $product,
        PriceObservation $latestObservation,
        ?PriceObservation $previousObservation = null,
    ): bool;
}
