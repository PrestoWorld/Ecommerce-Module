<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Events;

final class StockReserved extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly string $variantId,
        public readonly int $quantity,
        public readonly string $orderId,
        public readonly string $reservationId,
    ) {
        parent::__construct(payload: [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'order_id' => $orderId,
            'reservation_id' => $reservationId,
        ]);
    }
}

final class StockReleased extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly string $variantId,
        public readonly int $quantity,
        public readonly string $reservationId,
        public readonly string $reason,
    ) {
        parent::__construct(payload: [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'reservation_id' => $reservationId,
            'reason' => $reason,
        ]);
    }
}

final class StockAdjusted extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly string $variantId,
        public readonly int $quantityBefore,
        public readonly int $quantityAfter,
        public readonly string $reason,
        public readonly ?string $referenceId = null,
    ) {
        parent::__construct(payload: [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'reference_id' => $referenceId,
        ]);
    }
}

final class LowStockAlert extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly string $variantId,
        public readonly int $currentStock,
        public readonly int $reorderPoint,
    ) {
        parent::__construct(payload: [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'current_stock' => $currentStock,
            'reorder_point' => $reorderPoint,
        ]);
    }
}