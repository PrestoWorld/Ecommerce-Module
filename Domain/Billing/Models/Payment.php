<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Models;

use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Models\Money;
use JsonSerializable;

/**
 * Payment - Payment record
 */
final class Payment implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $externalId,
        public readonly string $invoiceId,
        public readonly string $invoiceExternalId,
        public readonly string $businessId,
        public readonly string $number, // payment reference number
        public readonly Money $amount,
        public readonly string $currency,
        public readonly string $method, // cash, card, bank_transfer, momo, zalopay, vnpay, cod, wallet
        public readonly string $gateway, // gateway used (if applicable)
        public readonly string $status, // pending, processing, success, failed, refunded, cancelled
        public readonly ?string $transactionId = null,
        public readonly ?string $gatewayTransactionId = null,
        public readonly ?string $gatewayResponse = null,
        public readonly ?string $paidBy = null, // customer, admin, system
        public readonly ?string $paidById = null,
        public readonly array $metadata = [],
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?int $completedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            externalId: (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            invoiceId: (string) ($data['invoice_id'] ?? $data['invoiceId'] ?? ''),
            invoiceExternalId: (string) ($data['invoice_external_id'] ?? $data['invoiceExternalId'] ?? ''),
            businessId: (string) ($data['business_id'] ?? $data['businessId'] ?? ''),
            number: (string) ($data['number'] ?? ''),
            amount: $data['amount'] instanceof Money ? $data['amount'] : Money::fromVnd((int) ($data['amount'] ?? 0)),
            currency: (string) ($data['currency'] ?? 'VND'),
            method: (string) ($data['method'] ?? ''),
            gateway: (string) ($data['gateway'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            transactionId: $data['transaction_id'] ?? $data['transactionId'] ?? null,
            gatewayTransactionId: $data['gateway_transaction_id'] ?? $data['gatewayTransactionId'] ?? null,
            gatewayResponse: $data['gateway_response'] ?? $data['gatewayResponse'] ?? null,
            paidBy: $data['paid_by'] ?? $data['paidBy'] ?? null,
            paidById: $data['paid_by_id'] ?? $data['paidById'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: (int) ($data['created_at'] ?? $data['createdAt'] ?? time()),
            updatedAt: (int) ($data['updated_at'] ?? $data['updatedAt'] ?? time()),
            completedAt: $data['completed_at'] ?? $data['completedAt'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'invoice_id' => $this->invoiceId,
            'invoice_external_id' => $this->invoiceExternalId,
            'business_id' => $this->businessId,
            'number' => $this->number,
            'amount' => $this->amount->toInt(),
            'currency' => $this->currency,
            'method' => $this->method,
            'gateway' => $this->gateway,
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'gateway_response' => $this->gatewayResponse,
            'paid_by' => $this->paidBy,
            'paid_by_id' => $this->paidById,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'completed_at' => $this->completedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}