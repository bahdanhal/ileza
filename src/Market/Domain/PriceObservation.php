<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class PriceObservation
{
    public readonly string $availability;

    public function __construct(
        public string $productSlug,
        public \DateTimeImmutable $observedAt,
        public int $medianGrosz,
        public int $lowGrosz,
        public int $highGrosz,
        string $availability,
        public string $summary,
        public string $methodology,
    ) {
        // Accept historical serialized values while callers migrate to availability.
        if (in_array($availability, ['low', 'medium', 'high'], true)) {
            $availability = 'available';
        }
        $this->availability = $availability;
        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            throw new \InvalidArgumentException('Observed prices are inconsistent.');
        }
        if (!in_array($availability, ['available', 'unavailable'], true)) {
            throw new \InvalidArgumentException('Observation availability must be available or unavailable.');
        }
    }

    /**
     * @return array{
     *     product_slug: string,
     *     observed_at: string,
     *     median_grosz: int,
     *     low_grosz: int,
     *     high_grosz: int,
     *     availability: string,
     *     summary: string,
     *     methodology: string
     * }
     */
    public function toArray(): array
    {
        return [
            'product_slug' => $this->productSlug,
            'observed_at' => $this->observedAt->format(DATE_ATOM),
            'median_grosz' => $this->medianGrosz,
            'low_grosz' => $this->lowGrosz,
            'high_grosz' => $this->highGrosz,
            'availability' => $this->availability,
            'summary' => $this->summary,
            'methodology' => $this->methodology,
        ];
    }

    public const METHODOLOGY_MANUAL =
        'Manual editorial estimate based on product knowledge and available market information; '
        . 'no statistical sample or automated pricing algorithm is claimed.';

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['product_slug'],
            new \DateTimeImmutable((string) $data['observed_at']),
            (int) $data['median_grosz'],
            (int) $data['low_grosz'],
            (int) $data['high_grosz'],
            isset($data['availability']) ? (string) $data['availability'] : 'available',
            '',
            self::METHODOLOGY_MANUAL
        );
    }
}
