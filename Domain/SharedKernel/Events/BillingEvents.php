<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Events;

final class InvoiceCreated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly float $amount,
        public readonly string $currency = 'VND',
        public readonly string $type = 'sale', // sale, refund, credit
    ) {
        parent::__construct(payload: [
            'invoice_id' => $invoiceId,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
        ]);
    }
}

final class InvoicePaid extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly float $paidAmount,
        public readonly string $paymentMethod,
        public readonly string $transactionId,
    ) {
        parent::__construct(payload: [
            'invoice_id' => $invoiceId,
            'paid_amount' => $paidAmount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
        ]);
    }
}

final class PaymentProcessed extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $invoiceId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $gateway,
        public readonly string $status, // success, failed, pending
        public readonly ?string $errorMessage = null,
    ) {
        parent::__construct(payload: [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gateway,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}

final class RefundRequested extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $invoiceId,
        public readonly float $amount,
        public readonly string $reason,
        public readonly string $requestedBy,
    ) {
        parent::__construct(payload: [
            'refund_id' => $refundId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);
    }
}