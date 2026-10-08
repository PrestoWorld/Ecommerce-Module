<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Models;

use JsonSerializable;

/**
 * Product Variant Option - Physical attribute variations within a variant
 * 
 * Examples: Hardcover vs Softcover, New vs Used, Physical vs Ebook vs Audiobook
 * Each option has its own SKU, barcode, pricing, and inventory
 */
final class ProductVariantOption implements JsonSerializable
{
    public function __construct(
        public readonly string $externalId,
        public readonly int $variantId,
        public readonly int $masterId,
        public readonly string $sku,
        public readonly ?string $barcode,
        public readonly ?string $bindingType,    // bìa cứng, bìa mềm, bìa carton, spiral
        public readonly string $condition,       // new, used_like_new, used_good, used_fair, damaged
        public readonly string $format,          // physical, ebook, audiobook, pdf
        public readonly ?string $color,
        public readonly ?string $size,
        public readonly array $attributes,       // flexible additional attributes
        public readonly ?int $coverPrice,
        public readonly ?int $wholesalePrice,
        public readonly ?int $costPrice,
        public readonly string $currency,
        public readonly int $stockOnHand,
        public readonly int $stockReserved,
        public readonly int $stockAvailable,
        public readonly int $reorderPoint,
        public readonly int $reorderQty,
        public readonly ?string $location,
        public readonly ?string $supplierId,
        public readonly ?string $supplierSku,
        public readonly ?int $weight,
        public readonly ?string $dimensions,
        public readonly ?string $coverImage,
        public readonly array $images,
        public readonly string $status,          // active, discontinued, out_of_stock
        public readonly bool $isDefault,
        public readonly int $sortOrder,
        public readonly array $payload,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $id = null,
        public readonly ?string $businessId = null,
        public readonly ?string $variantExternalId = null,
        public readonly ?string $masterExternalId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            variantId: (int) ($data['variant_id'] ?? $data['variantId'] ?? 0),
            masterId: (int) ($data['master_id'] ?? $data['masterId'] ?? 0),
            sku: (string) ($data['sku'] ?? ''),
            barcode: $data['barcode'] ?? null,
            bindingType: $data['binding_type'] ?? $data['bindingType'] ?? null,
            condition: (string) ($data['condition'] ?? 'new'),
            format: (string) ($data['format'] ?? 'physical'),
            color: $data['color'] ?? null,
            size: $data['size'] ?? null,
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            coverPrice: $data['cover_price'] ?? $data['coverPrice'] ?? null,
            wholesalePrice: $data['wholesale_price'] ?? $data['wholesalePrice'] ?? null,
            costPrice: $data['cost_price'] ?? $data['costPrice'] ?? null,
            currency: (string) ($data['currency'] ?? 'VND'),
            stockOnHand: (int) ($data['stock_on_hand'] ?? $data['stockOnHand'] ?? 0),
            stockReserved: (int) ($data['stock_reserved'] ?? $data['stockReserved'] ?? 0),
            stockAvailable: (int) ($data['stock_available'] ?? $data['stockAvailable'] ?? 0),
            reorderPoint: (int) ($data['reorder_point'] ?? $data['reorderPoint'] ?? 0),
            reorderQty: (int) ($data['reorder_qty'] ?? $data['reorderQty'] ?? 0),
            location: $data['location'] ?? null,
            supplierId: $data['supplier_id'] ?? $data['supplierId'] ?? null,
            supplierSku: $data['supplier_sku'] ?? $data['supplierSku'] ?? null,
            weight: $data['weight'] ?? null,
            dimensions: $data['dimensions'] ?? null,
            coverImage: $data['cover_image'] ?? $data['coverImage'] ?? null,
            images: is_array($data['images'] ?? null) ? $data['images'] : [],
            status: (string) ($data['status'] ?? 'active'),
            isDefault: (bool) ($data['is_default'] ?? $data['isDefault'] ?? false),
            sortOrder: (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? 0),
            id: $data['id'] ?? null,
            businessId: $data['business_id'] ?? $data['businessId'] ?? null,
            variantExternalId: $data['variant_external_id'] ?? $data['variantExternalId'] ?? null,
            masterExternalId: $data['master_external_id'] ?? $data['masterExternalId'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->businessId,
            'external_id' => $this->externalId,
            'variant_id' => $this->variantId,
            'master_id' => $this->masterId,
            'variant_external_id' => $this->variantExternalId,
            'master_external_id' => $this->masterExternalId,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'binding_type' => $this->bindingType,
            'condition' => $this->condition,
            'format' => $this->format,
            'color' => $this->color,
            'size' => $this->size,
            'attributes' => $this->attributes,
            'cover_price' => $this->coverPrice,
            'wholesale_price' => $this->wholesalePrice,
            'cost_price' => $this->costPrice,
            'currency' => $this->currency,
            'stock_on_hand' => $this->stockOnHand,
            'stock_reserved' => $this->stockReserved,
            'stock_available' => $this->stockAvailable,
            'reorder_point' => $this->reorderPoint,
            'reorder_qty' => $this->reorderQty,
            'location' => $this->location,
            'supplier_id' => $this->supplierId,
            'supplier_sku' => $this->supplierSku,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'cover_image' => $this->coverImage,
            'images' => $this->images,
            'status' => $this->status,
            'is_default' => $this->isDefault,
            'sort_order' => $this->sortOrder,
            'payload' => $this->payload,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getDisplayName(): string
    {
        $parts = [];
        if ($this->bindingType) $parts[] = $this->bindingType;
        if ($this->condition !== 'new') $parts[] = $this->getConditionLabel();
        if ($this->format !== 'physical') $parts[] = $this->getFormatLabel();
        if ($this->color) $parts[] = $this->color;
        return implode(' - ', $parts) ?: 'Default';
    }

    public function getConditionLabel(): string
    {
        return match ($this->condition) {
            'new' => 'Mới',
            'used_like_new' => 'Cũ như mới',
            'used_good' => 'Cũ tốt',
            'used_fair' => 'Cũ khá',
            'damaged' => 'Hỏng',
            default => $this->condition,
        };
    }

    public function getFormatLabel(): string
    {
        return match ($this->format) {
            'physical' => 'Sách vật lý',
            'ebook' => 'Ebook',
            'audiobook' => 'Audiobook',
            'pdf' => 'PDF',
            default => $this->format,
        };
    }

    public function isInStock(): bool
    {
        return $this->stockAvailable > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stockAvailable <= $this->reorderPoint && $this->reorderPoint > 0;
    }

    public function getEffectiveCoverPrice(?int $variantCoverPrice = null): int
    {
        return $this->coverPrice ?? $variantCoverPrice ?? 0;
    }

    public function getEffectiveWholesalePrice(?int $variantWholesalePrice = null): int
    {
        return $this->wholesalePrice ?? $variantWholesalePrice ?? 0;
    }

    public function getEffectiveCostPrice(?int $variantCostPrice = null): int
    {
        return $this->costPrice ?? $variantCostPrice ?? 0;
    }

    public function getMargin(?int $variantCostPrice = null): int
    {
        return $this->getEffectiveCoverPrice() - $this->getEffectiveCostPrice($variantCostPrice);
    }

    public function canReserve(int $quantity): bool
    {
        return $this->stockAvailable >= $quantity;
    }

    public function getOptionKey(): string
    {
        $key = $this->format;
        if ($this->bindingType) $key .= '|' . $this->bindingType;
        if ($this->condition !== 'new') $key .= '|' . $this->condition;
        return $key;
    }
}