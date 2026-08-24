<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Money\Currency;
use Money\Money;

final readonly class Grosz
{
    private Money $money;

    public function __construct(public int $amount)
    {
        $this->money = new Money($amount, new Currency('PLN'));
    }

    public static function fromGrosz(int $amount): self
    {
        return new self($amount);
    }

    public static function fromZloty(float|int|string $pln): self
    {
        $floatVal = (float) $pln;
        return new self((int) round($floatVal * 100));
    }

    public static function fromMoney(Money $money): self
    {
        return new self((int) $money->getAmount());
    }

    public function toMoney(): Money
    {
        return $this->money;
    }

    public function toPln(): float
    {
        return $this->amount / 100;
    }

    public function toSmartRoundedPlnValue(): int
    {
        $pln = $this->toPln();
        if ($pln <= 0.0) {
            return 0;
        }

        if ($pln < 100.0) {
            return (int) (round($pln / 5.0) * 5);
        }

        if ($pln < 2000.0) {
            return (int) (round($pln / 10.0) * 10);
        }

        if ($pln <= 20000.0) {
            return (int) (round($pln / 100.0) * 100);
        }

        return (int) (round($pln / 500.0) * 500);
    }

    public function toSmartRoundedPln(string $locale = 'pl'): string
    {
        $rounded = $this->toSmartRoundedPlnValue();
        if ($locale === 'pl') {
            return number_format($rounded, 0, ',', ' ') . ' zł';
        }

        return number_format($rounded, 0, '.', ',') . ' PLN';
    }

    public function toFormattedPln(string $locale = 'pl'): string
    {
        $pln = $this->toPln();
        if ($locale === 'pl') {
            return number_format($pln, 2, ',', ' ') . ' zł';
        }

        return 'PLN ' . number_format($pln, 2, '.', ',');
    }

    public function add(self $other): self
    {
        return self::fromMoney($this->money->add($other->toMoney()));
    }

    public function subtract(self $other): self
    {
        return self::fromMoney($this->money->subtract($other->toMoney()));
    }

    public function multiply(float|int $factor): self
    {
        return new self((int) round($this->amount * $factor));
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->money->greaterThan($other->toMoney());
    }

    public function isLessThan(self $other): bool
    {
        return $this->money->lessThan($other->toMoney());
    }

    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    public function equals(self $other): bool
    {
        return $this->money->equals($other->toMoney());
    }
}
