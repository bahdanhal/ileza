<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;

final readonly class RecordPriceObservation
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
        private ?CheckPriceAlerts $checkPriceAlerts = null,
    ) {
    }

    public function execute(
        string $slug,
        \DateTimeImmutable $observedAt,
        int $medianGrosz,
        int $lowGrosz,
        int $highGrosz,
        string $availability = 'available',
        string $summary = '',
        string $methodology = PriceObservation::METHODOLOGY_MANUAL,
    ): PriceObservation {
        if ($this->catalog->get($slug) === null) {
            throw new \InvalidArgumentException(sprintf('Unknown product slug: %s', $slug));
        }

        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz <= 0 || $lowGrosz > $medianGrosz) {
            throw new \InvalidArgumentException('Inconsistent prices. Ensure positive prices, low <= median, and high >= low.');
        }

        $highGrosz = min($highGrosz, $medianGrosz);

        if ($lowGrosz >= $highGrosz) {
            if ($lowGrosz > $highGrosz) {
                throw new \InvalidArgumentException('Inconsistent prices. Ensure positive prices, low <= median, and high >= low.');
            }

            $highGrosz = max($highGrosz, (int) round($lowGrosz * 1.12));
        }

        if ($medianGrosz < $lowGrosz) {
            throw new \InvalidArgumentException('Inconsistent prices. Ensure low <= high <= median and median > 0.');
        }

        $observation = new PriceObservation(
            $slug,
            $observedAt,
            $medianGrosz,
            $lowGrosz,
            $highGrosz,
            $availability,
            $summary,
            $methodology !== '' ? $methodology : PriceObservation::METHODOLOGY_MANUAL,
        );

        $this->observations->save($observation);

        if ($this->checkPriceAlerts !== null) {
            try {
                $this->checkPriceAlerts->execute($slug);
            } catch (\Throwable) {
                // Background alert check failure should not block observation persistence
            }
        }

        return $observation;
    }
}
