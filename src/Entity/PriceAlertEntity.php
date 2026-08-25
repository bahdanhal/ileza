<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_alerts')]
#[ORM\Index(columns: ['product_slug'], name: 'idx_alerts_product_slug')]
#[ORM\Index(columns: ['email'], name: 'idx_alerts_email')]
#[ORM\Index(columns: ['verification_token'], name: 'idx_alerts_verification_token')]
#[ORM\Index(columns: ['unsubscribe_token'], name: 'idx_alerts_unsubscribe_token')]
#[ORM\Index(columns: ['status'], name: 'idx_alerts_status')]
class PriceAlertEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $productSlug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $targetPriceGrosz;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $verificationToken;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $unsubscribeToken;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifiedAt;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipHash;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $locale;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastNotifiedAt;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $lastNotifiedPriceGrosz;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    public function __construct(
        string $productSlug,
        string $email,
        ?int $targetPriceGrosz,
        string $verificationToken,
        string $unsubscribeToken,
        bool $isVerified,
        ?\DateTimeImmutable $verifiedAt,
        string $ipHash,
        string $locale,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $lastNotifiedAt = null,
        ?int $lastNotifiedPriceGrosz = null,
        string $status = 'pending',
    ) {
        $this->productSlug = $productSlug;
        $this->email = $email;
        $this->targetPriceGrosz = $targetPriceGrosz;
        $this->verificationToken = $verificationToken;
        $this->unsubscribeToken = $unsubscribeToken;
        $this->isVerified = $isVerified;
        $this->verifiedAt = $verifiedAt;
        $this->ipHash = $ipHash;
        $this->locale = $locale;
        $this->createdAt = $createdAt;
        $this->lastNotifiedAt = $lastNotifiedAt;
        $this->lastNotifiedPriceGrosz = $lastNotifiedPriceGrosz;
        $this->status = $status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductSlug(): string
    {
        return $this->productSlug;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTargetPriceGrosz(): ?int
    {
        return $this->targetPriceGrosz;
    }

    public function setTargetPriceGrosz(?int $targetPriceGrosz): void
    {
        $this->targetPriceGrosz = $targetPriceGrosz;
    }

    public function getVerificationToken(): string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(string $verificationToken): void
    {
        $this->verificationToken = $verificationToken;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): void
    {
        $this->verifiedAt = $verifiedAt;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function setLastNotifiedAt(?\DateTimeImmutable $lastNotifiedAt): void
    {
        $this->lastNotifiedAt = $lastNotifiedAt;
    }

    public function getLastNotifiedPriceGrosz(): ?int
    {
        return $this->lastNotifiedPriceGrosz;
    }

    public function setLastNotifiedPriceGrosz(?int $lastNotifiedPriceGrosz): void
    {
        $this->lastNotifiedPriceGrosz = $lastNotifiedPriceGrosz;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
