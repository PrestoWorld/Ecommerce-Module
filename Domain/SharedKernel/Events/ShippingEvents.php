<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Events;

final class ShipmentCreated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $orderId,
        public readonly string $carrier,
        public readonly string $trackingNumber,
        public readonly array $items,
        public readonly float $shippingFee,
        public readonly string $currency = 'VND',
    ) {
        parent::__construct(payload: [
            'shipment_id' => $shipmentId,
            'order_id' => $orderId,
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'items' => $items,
            'shipping_fee' => $shippingFee,
            'currency' => $currency,
        ]);
    }
}

final class ShipmentStatusUpdated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $location = null,
        public readonly ?string $note = null,
    ) {
        parent::__construct(payload: [
            'shipment_id' => $shipmentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'location' => $location,
            'note' => $note,
        ]);
    }
}

final class ShipmentDelivered extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $orderId,
        public readonly \DateTimeImmutable $deliveredAt,
        public readonly ?string $recipientName = null,
        public readonly ?string $proofOfDelivery = null,
    ) {
        parent::__construct(payload: [
            'shipment_id' => $shipmentId,
            'order_id' => $orderId,
            'delivered_at' => $deliveredAt->format('c'),
            'recipient_name' => $recipientName,
            'proof_of_delivery' => $proofOfDelivery,
        ]);
    }
}

final class ReturnRequested extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $returnId,
        public readonly string $orderId,
        public readonly array $items,
        public readonly string $reason,
        public readonly string $requestedBy,
    ) {
        parent::__construct(payload: [
            'return_id' => $returnId,
            'order_id' => $orderId,
            'items' => $items,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);
    }
}