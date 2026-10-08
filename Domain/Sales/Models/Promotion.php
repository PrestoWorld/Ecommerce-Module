<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Sales\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Promotion - Discount/promotion rules
 */
final class Promotion implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $code, // human-readable promotion code
        public readonly string $name,
        public readonly string $type, // percentage, fixed_amount, buy_x_get_y, free_shipping, bundle
        public readonly string $scope, // order, product, category, cart, shipping
        public readonly Money $value, // discount amount or percentage
        public readonly ?Money $minOrderAmount = null,
        public readonly ?Money $maxDiscountAmount = null,
        public readonly int $usageLimit = 0, // 0 = unlimited
        public readonly int $usageCount = 0,
        public readonly int $perCustomerLimit = 0,
        public readonly array $applicableProducts = [], // product/variant external IDs
        public readonly array $applicableCategories = [],
        public readonly array $excludedProducts = [],
        public readonly array $excludedCategories = [],
        public readonly array $customerTiers = [], // which tiers can use this
        public readonly int $startsAt,
        public readonly int $endsAt,
        public readonly bool $isActive = true,
        public readonly bool $isStackable = false, // can combine with other promotions
        public readonly int $priority = 0,
        public readonly array $conditions = [], // complex conditions
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
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? 'percentage'),
            scope: (string) ($data['scope'] ?? 'order'),
            value: $data['value'] instanceof Money ? $data['value'] : Money::fromVnd((int) ($data['value'] ?? 0)),
            minOrderAmount: isset($data['min_order_amount']) && $data['min_order_amount'] instanceof Money ? $data['min_order_amount'] : (isset($data['min_order_amount']) ? Money::fromVnd((int) $data['min_order_amount']) : null),
            maxDiscountAmount: isset($data['max_discount_amount']) && $data['max_discount_amount'] instanceof Money ? $data['max_discount_amount'] : (isset($data['max_discount_amount']) ? Money::fromVnd((int) $data['max_discount_amount']) : null),
            usageLimit: (int) ($data['usage_limit'] ?? $data['usageLimit'] ?? 0),
            usageCount: (int) ($data['usage_count'] ?? $data['usageCount'] ?? 0),
            perCustomerLimit: (int) ($data['per_customer_limit'] ?? $data['perCustomerLimit'] ?? 0),
            applicableProducts: is_array($data['applicable_products'] ?? $data['applicableProducts'] ?? null) ? $data['applicable_products'] ?? $data['applicableProducts'] : [],
            applicableCategories: is_array($data['applicable_categories'] ?? $data['applicableCategories'] ?? null) ? $data['applicable_categories'] ?? $data['applicableCategories'] : [],
            excludedProducts: is_array($data['excluded_products'] ?? $data['excludedProducts'] ?? null) ? $data['excluded_products'] ?? $data['excludedProducts'] : [],
            excludedCategories: is_array($data['excluded_categories'] ?? $data['excludedCategories'] ?? null) ? $data['excluded_categories'] ?? $data['excludedCategories'] : [],
            customerTiers: is_array($data['customer_tiers'] ?? $data['customerTiers'] ?? null) ? $data['customer_tiers'] ?? $data['customerTiers'] : [],
            startsAt: (int) ($data['starts_at'] ?? $data['startsAt'] ?? time()),
            endsAt: (int) ($data['ends_at'] ?? $data['endsAt'] ?? (time() + 86400 * 365)),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            isStackable: (bool) ($data['is_stackable'] ?? $data['isStackable'] ?? false),
            priority: (int) ($data['priority'] ?? 0),
            conditions: is_array($data['conditions'] ?? null) ? $data['conditions'] : [],
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'scope' => $this->scope,
            'value' => $this->value->toInt(),
            'min_order_amount' => $this->minOrderAmount?->toInt(),
            'max_discount_amount' => $this->maxDiscountAmount?->toInt(),
            'usage_limit' => $this->usageLimit,
            'usage_count' => $this->usageCount,
            'per_customer_limit' => $this->perCustomerLimit,
            'applicable_products' => $this->applicableProducts,
            'applicable_categories' => $this->applicableCategories,
            'excluded_products' => $this->excludedProducts,
            'excluded_categories' => $this->excludedCategories,
            'customer_tiers' => $this->customerTiers,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'is_active' => $this->isActive,
            'is_stackable' => $this->isStackable,
            'priority' => $this->priority,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isValidAt(int $timestamp = 0): bool
    {
        $timestamp = $timestamp ?: time();
        return $this->isActive
            && $timestamp >= $this->startsAt
            && $timestamp <= $this->endsAt
            && ($this->usageLimit === 0 || $this->usageCount < $this->usageLimit);
    }

    public function canApplyToCustomer(string $customerTier, int $customerUsageCount = 0): bool
    {
        if (!empty($this->customerTiers) && !in_array($customerTier, $this->customerTiers, true)) {
            return false;
        }
        if ($this->perCustomerLimit > 0 && $customerUsageCount >= $this->perCustomerLimit) {
            return false;
        }
        return true;
    }

    public function calculateDiscount(Money $orderTotal, array $items = []): Money
    {
        if ($this->type === 'percentage') {
            return $orderTotal->percentage($this->value->toFloat());
        }
        if ($this->type === 'fixed_amount') {
            return $this->value;
        }
        // buy_x_get_y, free_shipping, bundle would need more complex logic
        return Money::fromVnd(0, $orderTotal->currency);
    }
}