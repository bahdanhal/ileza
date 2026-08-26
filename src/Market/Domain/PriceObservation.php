<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class PriceObservation
{
    public function __construct(
        public string $productSlug,
        public \DateTimeImmutable $observedAt,
        public int $medianGrosz,
        public int $lowGrosz,
        public int $highGrosz,
        public string $confidence,
        public string $summary,
        public string $methodology,
    ) {
        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            throw new \InvalidArgumentException('Observed prices are inconsistent.');
        }
        if (!in_array($confidence, ['low', 'medium', 'high'], true)) {
            throw new \InvalidArgumentException('Observation evidence is insufficient.');
        }
    }

    /**
     * @return array{
     *     product_slug: string,
     *     observed_at: string,
     *     median_grosz: int,
     *     low_grosz: int,
     *     high_grosz: int,
     *     confidence: string,
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
            'confidence' => $this->confidence,
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
            (string) $data['confidence'],
            '',
            self::METHODOLOGY_MANUAL
        );
    }
}
