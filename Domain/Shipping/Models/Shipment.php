<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Shipping\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Shipment - Delivery tracking
 */
final class Shipment implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $orderExternalId,
        public readonly string $carrierCode,
        public readonly string $carrierName,
        public readonly string $trackingNumber,
        public readonly string $status, // pending, picked_up, in_transit, out_for_delivery, delivered, failed, returned
        public readonly ?Money $shippingFee = null,
        public readonly ?Money $codAmount = null,
        public readonly ?Money $insuranceValue = null,
        public readonly array $items = [], // ShipmentItem[]
        public readonly ?string $pickupAddress = null,
        public readonly ?string $deliveryAddress = null,
        public readonly ?string $pickupContact = null,
        public readonly ?string $deliveryContact = null,
        public readonly ?int $pickedUpAt = null,
        public readonly ?int $deliveredAt = null,
        public readonly array $trackingHistory = [],
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
            orderExternalId: (string) ($data['order_external_id'] ?? $data['orderExternalId'] ?? ''),
            carrierCode: (string) ($data['carrier_code'] ?? $data['carrierCode'] ?? ''),
            carrierName: (string) ($data['carrier_name'] ?? $data['carrierName'] ?? ''),
            trackingNumber: (string) ($data['tracking_number'] ?? $data['trackingNumber'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            shippingFee: isset($data['shipping_fee']) && $data['shipping_fee'] instanceof Money ? $data['shipping_fee'] : (isset($data['shipping_fee']) ? Money::fromVnd((int) $data['shipping_fee']) : null),
            codAmount: isset($data['cod_amount']) && $data['cod_amount'] instanceof Money ? $data['cod_amount'] : (isset($data['cod_amount']) ? Money::fromVnd((int) $data['cod_amount']) : null),
            insuranceValue: isset($data['insurance_value']) && $data['insurance_value'] instanceof Money ? $data['insurance_value'] : (isset($data['insurance_value']) ? Money::fromVnd((int) $data['insurance_value']) : null),
            items: is_array($data['items'] ?? null) ? $data['items'] : [],
            pickupAddress: $data['pickup_address'] ?? $data['pickupAddress'] ?? null,
            deliveryAddress: $data['delivery_address'] ?? $data['deliveryAddress'] ?? null,
            pickupContact: $data['pickup_contact'] ?? $data['pickupContact'] ?? null,
            deliveryContact: $data['delivery_contact'] ?? $data['deliveryContact'] ?? null,
            pickedUpAt: $data['picked_up_at'] ?? $data['pickedUpAt'] ?? null,
            deliveredAt: $data['delivered_at'] ?? $data['deliveredAt'] ?? null,
            trackingHistory: is_array($data['tracking_history'] ?? $data['trackingHistory'] ?? null) ? $data['tracking_history'] ?? $data['trackingHistory'] : [],
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
            'order_external_id' => $this->orderExternalId,
            'carrier_code' => $this->carrierCode,
            'carrier_name' => $this->carrierName,
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status,
            'shipping_fee' => $this->shippingFee?->toInt(),
            'cod_amount' => $this->codAmount?->toInt(),
            'insurance_value' => $this->insuranceValue?->toInt(),
            'items' => $this->items,
            'pickup_address' => $this->pickupAddress,
            'delivery_address' => $this->deliveryAddress,
            'pickup_contact' => $this->pickupContact,
            'delivery_contact' => $this->deliveryContact,
            'picked_up_at' => $this->pickedUpAt,
            'delivered_at' => $this->deliveredAt,
            'tracking_history' => $this->trackingHistory,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, ['picked_up', 'in_transit', 'out_for_delivery'], true);
    }
}