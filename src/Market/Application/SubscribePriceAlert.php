<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceAlert;
use App\Market\Domain\PriceAlertRepository;

final readonly class SubscribePriceAlert
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceAlertRepository $priceAlerts,
        private PriceAlertMailerInterface $mailer,
    ) {
    }

    public function execute(
        string $productSlug,
        string $email,
        ?int $targetPriceGrosz,
        string $ipAddress,
        string $locale = 'pl',
    ): PriceAlert {
        $product = $this->catalog->get($productSlug);
        if ($product === null) {
            throw new \InvalidArgumentException(sprintf('Unknown product: %s', $productSlug));
        }

        $cleanEmail = strtolower(trim($email));
        if ($cleanEmail === '' || filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Valid email address is required.');
        }

        if ($targetPriceGrosz !== null && $targetPriceGrosz <= 0) {
            throw new \InvalidArgumentException('Target price must be greater than zero.');
        }

        $alert = $this->priceAlerts->subscribe(
            $productSlug,
            $cleanEmail,
            $targetPriceGrosz,
            $ipAddress,
            $locale,
        );

        if (!$alert->isVerified) {
            $this->mailer->sendVerificationEmail($alert, $product);
        }

        return $alert;
    }
}
