<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Contracts;

interface InvoiceRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByNumber(string $businessId, string $number): ?array;

    public function findByOrder(string $businessId, string $orderExternalId): array;

    public function save(string $businessId, array $data): array;

    public function updateStatus(string $businessId, string $externalId, string $status): array;

    public function markAsPaid(string $businessId, string $externalId, string $paymentId): array;

    public function getCustomerInvoices(string $businessId, string $customerExternalId, int $offset = 0, int $size = 20): array;

    public function getOverdueInvoices(string $businessId, int $offset = 0, int $size = 50): array;
}

interface PaymentRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByInvoice(string $businessId, string $invoiceExternalId): array;

    public function findByTransactionId(string $businessId, string $transactionId): ?array;

    public function save(string $businessId, array $data): array;

    public function updateStatus(string $businessId, string $externalId, string $status, array $gatewayData = []): array;
}