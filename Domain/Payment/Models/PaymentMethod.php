<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Payment\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Payment Method - Configured payment method/gateway
 */
final class PaymentMethod implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $code, // momo, zalopay, vnpay, payos, stripe, cod, bank_transfer, wallet
        public readonly string $name,
        public readonly string $type, // gateway, offline, wallet
        public readonly bool $isActive = true,
        public readonly bool $isDefault = false,
        public readonly array $config = [], // gateway-specific config (api keys, secrets, etc.)
        public readonly array $supportedCurrencies = ['VND'],
        public readonly ?Money $minAmount = null,
        public readonly ?Money $maxAmount = null,
        public readonly ?float $feePercent = null,
        public readonly ?Money $feeFixed = null,
        public readonly int $sortOrder = 0,
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
            type: (string) ($data['type'] ?? 'gateway'),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            isDefault: (bool) ($data['is_default'] ?? $data['isDefault'] ?? false),
            config: is_array($data['config'] ?? null) ? $data['config'] : [],
            supportedCurrencies: is_array($data['supported_currencies'] ?? $data['supportedCurrencies'] ?? null) ? $data['supported_currencies'] ?? $data['supportedCurrencies'] : ['VND'],
            minAmount: isset($data['min_amount']) && $data['min_amount'] instanceof Money ? $data['min_amount'] : (isset($data['min_amount']) ? Money::fromVnd((int) $data['min_amount']) : null),
            maxAmount: isset($data['max_amount']) && $data['max_amount'] instanceof Money ? $data['max_amount'] : (isset($data['max_amount']) ? Money::fromVnd((int) $data['max_amount']) : null),
            feePercent: $data['fee_percent'] ?? $data['feePercent'] ?? null,
            feeFixed: isset($data['fee_fixed']) && $data['fee_fixed'] instanceof Money ? $data['fee_fixed'] : (isset($data['fee_fixed']) ? Money::fromVnd((int) $data['fee_fixed']) : null),
            sortOrder: (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
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
            'is_active' => $this->isActive,
            'is_default' => $this->isDefault,
            'config' => $this->config,
            'supported_currencies' => $this->supportedCurrencies,
            'min_amount' => $this->minAmount?->toInt(),
            'max_amount' => $this->maxAmount?->toInt(),
            'fee_percent' => $this->feePercent,
            'fee_fixed' => $this->feeFixed?->toInt(),
            'sort_order' => $this->sortOrder,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function calculateFee(Money $amount): Money
    {
        $fee = Money::fromVnd(0, $amount->currency);
        if ($this->feePercent) {
            $fee = $fee->add($amount->percentage($this->feePercent));
        }
        if ($this->feeFixed) {
            $fee = $fee->add($this->feeFixed);
        }
        return $fee;
    }
}