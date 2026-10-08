<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Address;
use JsonSerializable;

/**
 * Order - Main order entity
 */
final class Order implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $code, // human-readable order number
        public readonly string $customerId,
        public readonly string $customerExternalId,
        public readonly string $status, // draft, pending, confirmed, processing, shipped, delivered, cancelled, refunded
        public readonly string $source, // website, pos, shopee, tiktok_shop, lazada, bookpress, manual
        public readonly ?string $channelOrderId, // order ID from channel
        public readonly array $items, // OrderItem[]
        public readonly Money $subtotal,
        public readonly Money $taxTotal,
        public readonly Money $discountTotal,
        public readonly Money $shippingFee,
        public readonly Money $total,
        public readonly ?Address $billingAddress,
        public readonly ?Address $shippingAddress,
        public readonly ?string $customerNote,
        public readonly ?string $internalNote,
        public readonly array $metadata = [], // flexible data
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $confirmedAt = null,
        public readonly ?int $shippedAt = null,
        public readonly ?int $deliveredAt = null,
        public readonly ?int $cancelledAt = null,
        public readonly ?string $cancelledReason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        foreach ($data['items'] ?? [] as $itemData) {
            $items[] = OrderItem::fromArray($itemData);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            customerId: (string) ($data['customer_id'] ?? $data['customerId'] ?? ''),
            customerExternalId: (string) ($data['customer_external_id'] ?? $data['customerExternalId'] ?? ''),
            status: (string) ($data['status'] ?? 'draft'),
            source: (string) ($data['source'] ?? 'manual'),
            channelOrderId: $data['channel_order_id'] ?? $data['channelOrderId'] ?? null,
            items: $items,
            subtotal: $data['subtotal'] instanceof Money ? $data['subtotal'] : Money::fromVnd((int) ($data['subtotal'] ?? 0)),
            taxTotal: $data['tax_total'] instanceof Money ? $data['tax_total'] : Money::fromVnd((int) ($data['tax_total'] ?? 0)),
            discountTotal: $data['discount_total'] instanceof Money ? $data['discount_total'] : Money::fromVnd((int) ($data['discount_total'] ?? 0)),
            shippingFee: $data['shipping_fee'] instanceof Money ? $data['shipping_fee'] : Money::fromVnd((int) ($data['shipping_fee'] ?? 0)),
            total: $data['total'] instanceof Money ? $data['total'] : Money::fromVnd((int) ($data['total'] ?? 0)),
            billingAddress: isset($data['billing_address']) ? Address::fromArray($data['billing_address']) : null,
            shippingAddress: isset($data['shipping_address']) ? Address::fromArray($data['shipping_address']) : null,
            customerNote: $data['customer_note'] ?? $data['customerNote'] ?? null,
            internalNote: $data['internal_note'] ?? $data['internalNote'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? 0),
            confirmedAt: $data['confirmed_at'] ?? $data['confirmedAt'] ?? null,
            shippedAt: $data['shipped_at'] ?? $data['shippedAt'] ?? null,
            deliveredAt: $data['delivered_at'] ?? $data['deliveredAt'] ?? null,
            cancelledAt: $data['cancelled_at'] ?? $data['cancelledAt'] ?? null,
            cancelledReason: $data['cancelled_reason'] ?? $data['cancelledReason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'business_id' => $this->businessId,
            'code' => $this->code,
            'customer_id' => $this->customerId,
            'customer_external_id' => $this->customerExternalId,
            'status' => $this->status,
            'source' => $this->source,
            'channel_order_id' => $this->channelOrderId,
            'items' => array_map(fn ($i) => $i->toArray(), $this->items),
            'subtotal' => $this->subtotal->toInt(),
            'tax_total' => $this->taxTotal->toInt(),
            'discount_total' => $this->discountTotal->toInt(),
            'shipping_fee' => $this->shippingFee->toInt(),
            'total' => $this->total->toInt(),
            'billing_address' => $this->billingAddress?->toArray(),
            'shipping_address' => $this->shippingAddress?->toArray(),
            'customer_note' => $this->customerNote,
            'internal_note' => $this->internalNote,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'confirmed_at' => $this->confirmedAt,
            'shipped_at' => $this->shippedAt,
            'delivered_at' => $this->deliveredAt,
            'cancelled_at' => $this->cancelledAt,
            'cancelled_reason' => $this->cancelledReason,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'pending'], true);
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, ['confirmed', 'processing'], true);
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'confirmed'], true);
    }

    public function getTotalQuantity(): int
    {
        return array_sum(array_map(fn ($item) => $item->quantity, $this->items));
    }
}