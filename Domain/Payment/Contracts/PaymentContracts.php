<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Payment\Contracts;

interface PaymentMethodRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByCode(string $businessId, string $code): ?array;

    public function getActiveMethods(string $businessId, string $currency = 'VND'): array;

    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $externalId): bool;
}

interface PaymentTransactionRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByOrder(string $businessId, string $orderExternalId): array;

    public function findByGatewayTransactionId(string $businessId, string $gatewayTransactionId): ?array;

    public function save(string $businessId, array $data): array;

    public function updateStatus(string $businessId, string $externalId, string $status, array $gatewayResponse = []): array;
}

interface PaymentGatewayInterface
{
    public function getCode(): string;

    public function getName(): string;

    public function createPayment(array $data): array; // returns [transaction_id, redirect_url, ...]

    public function verifyPayment(array $data): array; // returns [status, amount, ...]

    public function refund(string $transactionId, int $amount, string $reason): array;

    public function handleCallback(array $data): array;
}