<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\PriceAlertMailerInterface;
use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;

final class MockPriceAlertMailer implements PriceAlertMailerInterface
{
    /** @var list<array{alert: PriceAlert, product: Product}> */
    public array $verificationEmails = [];

    /** @var list<array{alert: PriceAlert, product: Product, latest: PriceObservation, previous: ?PriceObservation}> */
    public array $dropNotifications = [];

    public function sendVerificationEmail(PriceAlert $alert, Product $product): bool
    {
        $this->verificationEmails[] = ['alert' => $alert, 'product' => $product];
        return true;
    }

    public function sendPriceDropNotification(
        PriceAlert $alert,
        Product $product,
        PriceObservation $latestObservation,
        ?PriceObservation $previousObservation = null,
    ): bool {
        $this->dropNotifications[] = [
            'alert' => $alert,
            'product' => $product,
            'latest' => $latestObservation,
            'previous' => $previousObservation,
        ];
        return true;
    }
}
