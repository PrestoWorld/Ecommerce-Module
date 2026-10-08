<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Invoice - Billing document
 */
final class Invoice implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $businessId,
        public readonly string $number, // human-readable invoice number
        public readonly string $orderId,
        public readonly string $orderExternalId,
        public readonly string $customerId,
        public readonly string $customerExternalId,
        public readonly string $type, // sale, refund, credit_note, debit_note
        public readonly string $status, // draft, issued, paid, partially_paid, overdue, cancelled, refunded
        public readonly Money $subtotal,
        public readonly Money $taxTotal,
        public readonly Money $discountTotal,
        public readonly Money $total,
        public readonly Money $paidAmount,
        public readonly Money $balanceDue,
        public readonly string $currency,
        public readonly int $issueDate,
        public readonly int $dueDate,
        public readonly ?int $paidAt = null,
        public readonly array $items = [], // InvoiceItem[]
        public readonly array $payments = [], // Payment[]
        public readonly ?string $notes = null,
        public readonly array $metadata = [],
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        foreach ($data['items'] ?? [] as $itemData) {
            $items[] = InvoiceItem::fromArray($itemData);
        }

        $payments = [];
        foreach ($data['payments'] ?? [] as $paymentData) {
            $payments[] = Payment::fromArray($paymentData);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            number: (string) ($data['number'] ?? ''),
            orderId: (string) ($data['order_id'] ?? $data['orderId'] ?? ''),
            orderExternalId: (string) ($data['order_external_id'] ?? $data['orderExternalId'] ?? ''),
            customerId: (string) ($data['customer_id'] ?? $data['customerId'] ?? ''),
            customerExternalId: (string) ($data['customer_external_id'] ?? $data['customerExternalId'] ?? ''),
            type: (string) ($data['type'] ?? 'sale'),
            status: (string) ($data['status'] ?? 'draft'),
            subtotal: $data['subtotal'] instanceof Money ? $data['subtotal'] : Money::fromVnd((int) ($data['subtotal'] ?? 0)),
            taxTotal: $data['tax_total'] instanceof Money ? $data['tax_total'] : Money::fromVnd((int) ($data['tax_total'] ?? 0)),
            discountTotal: $data['discount_total'] instanceof Money ? $data['discount_total'] : Money::fromVnd((int) ($data['discount_total'] ?? 0)),
            total: $data['total'] instanceof Money ? $data['total'] : Money::fromVnd((int) ($data['total'] ?? 0)),
            paidAmount: $data['paid_amount'] instanceof Money ? $data['paid_amount'] : Money::fromVnd((int) ($data['paid_amount'] ?? 0)),
            balanceDue: $data['balance_due'] instanceof Money ? $data['balance_due'] : Money::fromVnd((int) ($data['balance_due'] ?? 0)),
            currency: (string) ($data['currency'] ?? 'VND'),
            issueDate: (int) ($data['issue_date'] ?? $data['issueDate'] ?? time()),
            dueDate: (int) ($data['due_date'] ?? $data['dueDate'] ?? (time() + 86400 * 30)),
            paidAt: $data['paid_at'] ?? $data['paidAt'] ?? null,
            items: $items,
            payments: $payments,
            notes: $data['notes'] ?? null,
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
            'number' => $this->number,
            'order_id' => $this->orderId,
            'order_external_id' => $this->orderExternalId,
            'customer_id' => $this->customerId,
            'customer_external_id' => $this->customerExternalId,
            'type' => $this->type,
            'status' => $this->status,
            'subtotal' => $this->subtotal->toInt(),
            'tax_total' => $this->taxTotal->toInt(),
            'discount_total' => $this->discountTotal->toInt(),
            'total' => $this->total->toInt(),
            'paid_amount' => $this->paidAmount->toInt(),
            'balance_due' => $this->balanceDue->toInt(),
            'currency' => $this->currency,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'paid_at' => $this->paidAt,
            'items' => array_map(fn ($i) => $i->toArray(), $this->items),
            'payments' => array_map(fn ($p) => $p->toArray(), $this->payments),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->balanceDue->isZero();
    }

    public function isOverdue(): bool
    {
        if ($this->isPaid()) return false;
        return time() > $this->dueDate;
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) return 0;
        return (int) ((time() - $this->dueDate) / 86400) + 1;
    }
}