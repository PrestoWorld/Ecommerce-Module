<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel;

/**
 * Value Object: Phone
 */
final class Phone
{
    public function __construct(
        public readonly string $value,
        public readonly string $countryCode = 'VN',
    ) {
        $clean = preg_replace('/\D/', '', $value);
        
        // Normalize VN phone numbers
        if ($this->countryCode === 'VN') {
            if (str_starts_with($clean, '84')) {
                $clean = '0' . substr($clean, 2);
            } elseif (!str_starts_with($clean, '0') && strlen($clean) >= 9) {
                $clean = '0' . $clean;
            }
        }
        
        if (!preg_match('/^0\d{9,10}$/', $clean)) {
            throw new \InvalidArgumentException("Invalid phone number: $value");
        }
        
        $this->value = $clean;
    }

    public static function fromRaw(string $raw, string $countryCode = 'VN'): self
    {
        return new self($raw, $countryCode);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function format(): string
    {
        // Format: 0xx xxx xxxx
        if (strlen($this->value) === 10) {
            return substr($this->value, 0, 4) . ' ' . substr($this->value, 4, 3) . ' ' . substr($this->value, 7);
        }
        return substr($this->value, 0, 3) . ' ' . substr($this->value, 3, 3) . ' ' . substr($this->value, 6);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}