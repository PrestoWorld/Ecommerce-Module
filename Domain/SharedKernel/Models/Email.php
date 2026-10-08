<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel;

/**
 * Value Object: Email
 */
final class Email
{
    public function __construct(
        public readonly string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: $value");
        }
        $this->value = strtolower($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strrpos($this->value, '@') + 1);
    }
}