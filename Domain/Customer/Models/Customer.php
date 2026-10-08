<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Customer\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Address;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Email;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Phone;
use JsonSerializable;

/**
 * Customer - Unified customer profile
 */
final class Customer implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $code, // human-readable customer code
        public readonly string $fullName,
        public readonly ?Email $email,
        public readonly ?Phone $phone,
        public readonly ?Address $defaultBillingAddress,
        public readonly ?Address $defaultShippingAddress,
        public readonly string $status, // active, inactive, blocked, guest
        public readonly string $tier, // bronze, silver, gold, platinum, vip
        public readonly int $totalOrders = 0,
        public readonly int $totalSpent = 0, // in smallest currency unit
        public readonly int $loyaltyPoints = 0,
        public readonly ?string $source = null, // website, pos, referral, import
        public readonly ?string $referralCode = null,
        public readonly ?string $referredBy = null,
        public readonly array $tags = [],
        public readonly array $metadata = [],
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $lastOrderAt = null,
        public readonly ?int $lastLoginAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            fullName: (string) ($data['full_name'] ?? $data['fullName'] ?? ''),
            email: isset($data['email']) && $data['email'] instanceof Email ? $data['email'] : (isset($data['email']) ? new Email($data['email']) : null),
            phone: isset($data['phone']) && $data['phone'] instanceof Phone ? $data['phone'] : (isset($data['phone']) ? new Phone($data['phone']) : null),
            defaultBillingAddress: isset($data['default_billing_address']) && $data['default_billing_address'] instanceof Address ? $data['default_billing_address'] : (isset($data['default_billing_address']) ? Address::fromArray($data['default_billing_address']) : null),
            defaultShippingAddress: isset($data['default_shipping_address']) && $data['default_shipping_address'] instanceof Address ? $data['default_shipping_address'] : (isset($data['default_shipping_address']) ? Address::fromArray($data['default_shipping_address']) : null),
            status: (string) ($data['status'] ?? 'active'),
            tier: (string) ($data['tier'] ?? 'bronze'),
            totalOrders: (int) ($data['total_orders'] ?? $data['totalOrders'] ?? 0),
            totalSpent: (int) ($data['total_spent'] ?? $data['totalSpent'] ?? 0),
            loyaltyPoints: (int) ($data['loyalty_points'] ?? $data['loyaltyPoints'] ?? 0),
            source: $data['source'] ?? null,
            referralCode: $data['referral_code'] ?? $data['referralCode'] ?? null,
            referredBy: $data['referred_by'] ?? $data['referredBy'] ?? null,
            tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? time()),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? time()),
            lastOrderAt: $data['last_order_at'] ?? $data['lastOrderAt'] ?? null,
            lastLoginAt: $data['last_login_at'] ?? $data['lastLoginAt'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'business_id' => $this->businessId,
            'code' => $this->code,
            'full_name' => $this->fullName,
            'email' => $this->email?->__toString(),
            'phone' => $this->phone?->__toString(),
            'default_billing_address' => $this->defaultBillingAddress?->toArray(),
            'default_shipping_address' => $this->defaultShippingAddress?->toArray(),
            'status' => $this->status,
            'tier' => $this->tier,
            'total_orders' => $this->totalOrders,
            'total_spent' => $this->totalSpent,
            'loyalty_points' => $this->loyaltyPoints,
            'source' => $this->source,
            'referral_code' => $this->referralCode,
            'referred_by' => $this->referredBy,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_order_at' => $this->lastOrderAt,
            'last_login_at' => $this->lastLoginAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDisplayName(): string
    {
        return $this->fullName;
    }
}