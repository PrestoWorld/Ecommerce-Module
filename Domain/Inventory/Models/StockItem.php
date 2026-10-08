<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Stock Item - Inventory record for a product variant option at a location
 */
final class StockItem implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $variantOptionExternalId,
        public readonly string $locationId,
        public readonly string $locationCode,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $quantityAvailable,
        public readonly int $reorderPoint,
        public readonly int $reorderQuantity,
        public readonly ?Money $costPrice,
        public readonly ?Money $averageCost,
        public readonly string $status, // active, low_stock, out_of_stock, discontinued
        public readonly array $metadata = [],
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $lastCountedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            variantOptionExternalId: (string) ($data['variant_option_external_id'] ?? $data['variantOptionExternalId'] ?? ''),
            locationId: (string) ($data['location_id'] ?? $data['locationId'] ?? ''),
            locationCode: (string) ($data['location_code'] ?? $data['locationCode'] ?? ''),
            quantityOnHand: (int) ($data['quantity_on_hand'] ?? $data['quantityOnHand'] ?? 0),
            quantityReserved: (int) ($data['quantity_reserved'] ?? $data['quantityReserved'] ?? 0),
            quantityAvailable: (int) ($data['quantity_available'] ?? $data['quantityAvailable'] ?? 0),
            reorderPoint: (int) ($data['reorder_point'] ?? $data['reorderPoint'] ?? 0),
            reorderQuantity: (int) ($data['reorder_quantity'] ?? $data['reorderQuantity'] ?? 0),
            costPrice: isset($data['cost_price']) && $data['cost_price'] instanceof Money ? $data['cost_price'] : (isset($data['cost_price']) ? Money::fromVnd((int) $data['cost_price']) : null),
            averageCost: isset($data['average_cost']) && $data['average_cost'] instanceof Money ? $data['average_cost'] : (isset($data['average_cost']) ? Money::fromVnd((int) $data['average_cost']) : null),
            status: (string) ($data['status'] ?? 'active'),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? time()),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? time()),
            lastCountedAt: $data['last_counted_at'] ?? $data['lastCountedAt'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'business_id' => $this->businessId,
            'variant_option_external_id' => $this->variantOptionExternalId,
            'location_id' => $this->locationId,
            'location_code' => $this->locationCode,
            'quantity_on_hand' => $this->quantityOnHand,
            'quantity_reserved' => $this->quantityReserved,
            'quantity_available' => $this->quantityAvailable,
            'reorder_point' => $this->reorderPoint,
            'reorder_quantity' => $this->reorderQuantity,
            'cost_price' => $this->costPrice?->toInt(),
            'average_cost' => $this->averageCost?->toInt(),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_counted_at' => $this->lastCountedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isLowStock(): bool
    {
        return $this->quantityAvailable <= $this->reorderPoint && $this->reorderPoint > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantityAvailable <= 0;
    }

    public function canReserve(int $quantity): bool
    {
        return $this->quantityAvailable >= $quantity;
    }
}