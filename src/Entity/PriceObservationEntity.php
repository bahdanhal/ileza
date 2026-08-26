<?php

declare(strict_types=1);

namespace App\Entity;

use App\Market\Domain\PriceObservation;
use App\Shared\Domain\Grosz;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_observations')]
#[ORM\UniqueConstraint(name: 'uniq_product_observed_at', columns: ['product_slug', 'observed_at'])]
#[ORM\Index(columns: ['product_slug'], name: 'idx_observations_product_slug')]
class PriceObservationEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $productSlug;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $observedAt;

    #[ORM\Column(type: 'grosz')]
    private Grosz $medianGrosz;

    #[ORM\Column(type: 'grosz')]
    private Grosz $lowGrosz;

    #[ORM\Column(type: 'grosz')]
    private Grosz $highGrosz;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $confidence;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $methodology;

    public function __construct(
        string $productSlug,
        \DateTimeImmutable $observedAt,
        Grosz|int $medianGrosz,
        Grosz|int $lowGrosz,
        Grosz|int $highGrosz,
        string $confidence,
        ?string $summary,
        string $methodology
    ) {
        $this->productSlug = $productSlug;
        $this->observedAt = $observedAt;
        $this->medianGrosz = $medianGrosz instanceof Grosz ? $medianGrosz : Grosz::fromGrosz($medianGrosz);
        $this->lowGrosz = $lowGrosz instanceof Grosz ? $lowGrosz : Grosz::fromGrosz($lowGrosz);
        $this->highGrosz = $highGrosz instanceof Grosz ? $highGrosz : Grosz::fromGrosz($highGrosz);
        $this->confidence = $confidence;
        $this->summary = $summary;
        $this->methodology = $methodology;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductSlug(): string
    {
        return $this->productSlug;
    }

    public function getObservedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function getMedian(): Grosz
    {
        return $this->medianGrosz;
    }

    public function getMedianGrosz(): int
    {
        return $this->medianGrosz->amount;
    }

    public function getLow(): Grosz
    {
        return $this->lowGrosz;
    }

    public function getLowGrosz(): int
    {
        return $this->lowGrosz->amount;
    }

    public function getHigh(): Grosz
    {
        return $this->highGrosz;
    }

    public function getHighGrosz(): int
    {
        return $this->highGrosz->amount;
    }

    public function getConfidence(): string
    {
        return $this->confidence;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getMethodology(): string
    {
        return $this->methodology;
    }

    public function updateFromObservation(PriceObservation $observation): void
    {
        $this->medianGrosz = Grosz::fromGrosz($observation->medianGrosz);
        $this->lowGrosz = Grosz::fromGrosz($observation->lowGrosz);
        $this->highGrosz = Grosz::fromGrosz($observation->highGrosz);
        $this->confidence = $observation->confidence;
        $this->summary = $observation->summary !== '' ? $observation->summary : null;
        $this->methodology = $observation->methodology !== '' ? $observation->methodology : PriceObservation::METHODOLOGY_MANUAL;
    }
}
