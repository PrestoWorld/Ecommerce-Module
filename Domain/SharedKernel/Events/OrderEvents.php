<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Events;

final class OrderCreated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly string $businessId,
        public readonly float $totalAmount,
        public readonly string $currency = 'VND',
    ) {
        parent::__construct(payload: [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'business_id' => $businessId,
            'total_amount' => $totalAmount,
            'currency' => $currency,
        ]);
    }
}

final class OrderUpdated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly array $changes = [],
    ) {
        parent::__construct(payload: [
            'order_id' => $orderId,
            'status' => $status,
            'changes' => $changes,
        ]);
    }
}

final class OrderCancelled extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
        public readonly ?string $cancelledBy = null,
    ) {
        parent::__construct(payload: [
            'order_id' => $orderId,
            'reason' => $reason,
            'cancelled_by' => $cancelledBy,
        ]);
    }
}

final class OrderCompleted extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly float $finalAmount,
        public readonly string $currency = 'VND',
    ) {
        parent::__construct(payload: [
            'order_id' => $orderId,
            'final_amount' => $finalAmount,
            'currency' => $currency,
        ]);
    }
}