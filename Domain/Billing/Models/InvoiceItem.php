<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Invoice Item - Line item in an invoice
 */
final class InvoiceItem implements JsonSerializable
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productExternalId,
        public readonly string $variantId,
        public readonly string $variantExternalId,
        public readonly ?string $variantOptionId,
        public readonly ?string $variantOptionExternalId,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $description,
        public readonly int $quantity,
        public readonly Money $unitPrice,
        public readonly Money $taxRate, // percentage as Money (e.g., 10% = 1000)
        public readonly Money $taxAmount,
        public readonly Money $discountAmount,
        public readonly Money $totalPrice,
        public readonly array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (string) ($data['product_id'] ?? $data['productId'] ?? ''),
            productExternalId: (string) ($data['product_external_id'] ?? $data['productExternalId'] ?? ''),
            variantId: (string) ($data['variant_id'] ?? $data['variantId'] ?? ''),
            variantExternalId: (string) ($data['variant_external_id'] ?? $data['variantExternalId'] ?? ''),
            variantOptionId: $data['variant_option_id'] ?? $data['variantOptionId'] ?? null,
            variantOptionExternalId: $data['variant_option_external_id'] ?? $data['variantOptionExternalId'] ?? null,
            sku: (string) ($data['sku'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            quantity: (int) ($data['quantity'] ?? 1),
            unitPrice: $data['unit_price'] instanceof Money ? $data['unit_price'] : Money::fromVnd((int) ($data['unit_price'] ?? 0)),
            taxRate: $data['tax_rate'] instanceof Money ? $data['tax_rate'] : Money::fromVnd((int) ($data['tax_rate'] ?? 0)),
            taxAmount: $data['tax_amount'] instanceof Money ? $data['tax_amount'] : Money::fromVnd((int) ($data['tax_amount'] ?? 0)),
            discountAmount: $data['discount_amount'] instanceof Money ? $data['discount_amount'] : Money::fromVnd((int) ($data['discount_amount'] ?? 0)),
            totalPrice: $data['total_price'] instanceof Money ? $data['total_price'] : Money::fromVnd((int) ($data['total_price'] ?? 0)),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_external_id' => $this->productExternalId,
            'variant_id' => $this->variantId,
            'variant_external_id' => $this->variantExternalId,
            'variant_option_id' => $this->variantOptionId,
            'variant_option_external_id' => $this->variantOptionExternalId,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toInt(),
            'tax_rate' => $this->taxRate->toInt(),
            'tax_amount' => $this->taxAmount->toInt(),
            'discount_amount' => $this->discountAmount->toInt(),
            'total_price' => $this->totalPrice->toInt(),
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}