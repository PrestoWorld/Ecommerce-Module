<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Models;

use JsonSerializable;

/**
 * Product Variant - Inventory/Warehouse level variant
 * 
 * Different editions, print runs, publishers, years
 * Contains: edition, publication_year, publisher, cover_price, wholesale_price, stock
 * Used for warehouse management
 */
final class ProductVariant implements JsonSerializable
{
    public function __construct(
        public readonly string $externalId,
        public readonly int $masterId,
        public readonly string $sku,
        public readonly ?string $barcode,
        public readonly int $edition,
        public readonly ?string $editionName,
        public readonly ?int $publicationYear,
        public readonly ?string $publisher,
        public readonly ?int $printRun,
        public readonly ?int $printDate,
        public readonly int $coverPrice,
        public readonly int $wholesalePrice,
        public readonly int $costPrice,
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
        public readonly string $status, // active, discontinued, out_of_print
        public readonly bool $isDefault,
        public readonly array $payload,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $id = null,
        public readonly ?string $businessId = null,
        public readonly ?int $masterExternalId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            masterId: (int) ($data['master_id'] ?? $data['masterId'] ?? 0),
            sku: (string) ($data['sku'] ?? ''),
            barcode: $data['barcode'] ?? null,
            edition: (int) ($data['edition'] ?? 1),
            editionName: $data['edition_name'] ?? $data['editionName'] ?? null,
            publicationYear: $data['publication_year'] ?? $data['publicationYear'] ?? null,
            publisher: $data['publisher'] ?? null,
            printRun: $data['print_run'] ?? $data['printRun'] ?? null,
            printDate: $data['print_date'] ?? $data['printDate'] ?? null,
            coverPrice: (int) ($data['cover_price'] ?? $data['coverPrice'] ?? 0),
            wholesalePrice: (int) ($data['wholesale_price'] ?? $data['wholesalePrice'] ?? 0),
            costPrice: (int) ($data['cost_price'] ?? $data['costPrice'] ?? 0),
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
            status: (string) ($data['status'] ?? 'active'),
            isDefault: (bool) ($data['is_default'] ?? $data['isDefault'] ?? false),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? 0),
            id: $data['id'] ?? null,
            businessId: $data['business_id'] ?? $data['businessId'] ?? null,
            masterExternalId: $data['master_external_id'] ?? $data['masterExternalId'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->businessId,
            'external_id' => $this->externalId,
            'master_id' => $this->masterId,
            'master_external_id' => $this->masterExternalId,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'edition' => $this->edition,
            'edition_name' => $this->editionName,
            'publication_year' => $this->publicationYear,
            'publisher' => $this->publisher,
            'print_run' => $this->printRun,
            'print_date' => $this->printDate,
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
            'status' => $this->status,
            'is_default' => $this->isDefault,
            'payload' => $this->payload,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isInStock(): bool
    {
        return $this->stockAvailable > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stockAvailable <= $this->reorderPoint && $this->reorderPoint > 0;
    }

    public function getMargin(): int
    {
        return $this->coverPrice - $this->costPrice;
    }

    public function getMarginPercent(): float
    {
        if ($this->coverPrice <= 0) {
            return 0.0;
        }
        return round(($this->getMargin() / $this->coverPrice) * 100, 2);
    }

    public function canReserve(int $quantity): bool
    {
        return $this->stockAvailable >= $quantity;
    }
}