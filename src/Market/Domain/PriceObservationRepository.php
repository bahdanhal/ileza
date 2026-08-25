<?php

declare(strict_types=1);

namespace App\Market\Domain;

interface PriceObservationRepository
{
    public function save(PriceObservation $observation): void;

    /** @return list<PriceObservation> */
    public function history(string $productSlug): array;

    /**
     * @param list<string> $productSlugs
     * @return array<string, list<PriceObservation>>
     */
    public function histories(array $productSlugs): array;

    public function latest(string $productSlug): ?PriceObservation;

    public function delete(string $productSlug, string $date): void;
}
