<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts;

interface OrderRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByCode(string $businessId, string $code): ?array;

    public function findByChannelOrderId(string $businessId, string $channelOrderId): ?array;

    public function save(string $businessId, array $data): array;

    public function updateStatus(string $businessId, string $externalId, string $status, array $additionalData = []): array;

    public function cancel(string $businessId, string $externalId, string $reason): array;

    public function getCustomerOrders(string $businessId, string $customerExternalId, int $offset = 0, int $size = 20): array;
}

interface CartRepositoryInterface
{
    public function find(string $businessId, string $customerId): ?array;

    public function save(string $businessId, array $data): array;

    public function addItem(string $businessId, string $customerId, array $itemData): array;

    public function updateItem(string $businessId, string $customerId, string $itemKey, array $itemData): array;

    public function removeItem(string $businessId, string $customerId, string $itemKey): array;

    public function clear(string $businessId, string $customerId): bool;
}

interface QuoteRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function save(string $businessId, array $data): array;

    public function convertToOrder(string $businessId, string $quoteExternalId, array $orderData): array;
}