<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Tax\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Tax Rate - Tax rate configuration
 */
final class TaxRate implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $name,
        public readonly string $code, // e.g., VAT, GST, SALES_TAX
        public readonly float $rate, // percentage (e.g., 10.0 for 10%)
        public readonly string $type, // percentage, fixed
        public readonly string $scope, // national, regional, local
        public readonly ?string $region, // province/state code
        public readonly array $applicableCategories = [], // product categories this applies to
        public readonly array $exemptCategories = [], // categories exempt from this tax
        public readonly bool $isCompound = false, // whether this tax compounds on others
        public readonly int $priority = 0, // calculation order
        public readonly bool $isActive = true,
        public readonly int $validFrom,
        public readonly ?int $validUntil = null,
        public readonly array $metadata = [],
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            rate: (float) ($data['rate'] ?? 0),
            type: (string) ($data['type'] ?? 'percentage'),
            scope: (string) ($data['scope'] ?? 'national'),
            region: $data['region'] ?? null,
            applicableCategories: is_array($data['applicable_categories'] ?? $data['applicableCategories'] ?? null) ? $data['applicable_categories'] ?? $data['applicableCategories'] : [],
            exemptCategories: is_array($data['exempt_categories'] ?? $data['exemptCategories'] ?? null) ? $data['exempt_categories'] ?? $data['exemptCategories'] : [],
            isCompound: (bool) ($data['is_compound'] ?? $data['isCompound'] ?? false),
            priority: (int) ($data['priority'] ?? 0),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            validFrom: (int) ($data['valid_from'] ?? $data['validFrom'] ?? time()),
            validUntil: $data['valid_until'] ?? $data['validUntil'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? time()),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? time()),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'business_id' => $this->businessId,
            'name' => $this->name,
            'code' => $this->code,
            'rate' => $this->rate,
            'type' => $this->type,
            'scope' => $this->scope,
            'region' => $this->region,
            'applicable_categories' => $this->applicableCategories,
            'exempt_categories' => $this->exemptCategories,
            'is_compound' => $this->isCompound,
            'priority' => $this->priority,
            'is_active' => $this->isActive,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function calculate(Money $amount): Money
    {
        if ($this->type === 'fixed') {
            return Money::fromVnd((int) $this->rate);
        }
        return $amount->percentage($this->rate);
    }

    public function isApplicableToCategory(string $category): bool
    {
        if (in_array($category, $this->exemptCategories, true)) {
            return false;
        }
        if (empty($this->applicableCategories)) {
            return true;
        }
        return in_array($category, $this->applicableCategories, true);
    }

    public function isValidAt(int $timestamp): bool
    {
        if (!$this->isActive) return false;
        if ($timestamp < $this->validFrom) return false;
        if ($this->validUntil && $timestamp > $this->validUntil) return false;
        return true;
    }
}