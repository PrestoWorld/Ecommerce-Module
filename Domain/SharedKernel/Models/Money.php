<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel;

/**
 * Value Object: Money
 * Immutable representation of monetary amounts
 */
final class Money
{
    public function __construct(
        public readonly int $amount,      // in smallest unit (cents for VND = VND)
        public readonly string $currency = 'VND',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency must be 3-letter ISO code');
        }
    }

    public static function fromVnd(int $vnd): self
    {
        return new self($vnd, 'VND');
    }

    public static function fromString(string $amount, string $currency = 'VND'): self
    {
        $clean = preg_replace('/[^\d.,-]/', '', $amount);
        $clean = str_replace(',', '.', $clean);
        $amount = (int) round((float) $clean * 100); // assuming 2 decimal places
        return new self($amount, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function divide(int $divisor): self
    {
        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function percentage(float $percent): self
    {
        return $this->multiply($percent / 100);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function toInt(): int
    {
        return $this->amount;
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    public function __toString(): string
    {
        return number_format($this->amount / 100, 0, ',', '.') . ' ' . $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}