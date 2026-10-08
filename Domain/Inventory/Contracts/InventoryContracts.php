<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts;

interface StockRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByVariantOption(string $businessId, string $variantOptionExternalId): array;

    public function findByLocation(string $businessId, string $locationId): array;

    public function getAggregateStock(string $businessId, string $variantOptionExternalId): array;

    public function save(string $businessId, array $data): array;

    public function adjustStock(string $businessId, string $externalId, int $quantity, string $operation, string $reason, ?string $referenceId = null): array;

    public function reserveStock(string $businessId, string $variantOptionExternalId, string $locationId, int $quantity, string $reservationId): array;

    public function releaseStock(string $businessId, string $variantOptionExternalId, string $locationId, int $quantity, string $reservationId, string $reason): array;

    public function transferStock(string $businessId, string $variantOptionExternalId, string $fromLocationId, string $toLocationId, int $quantity, string $reason): array;
}

interface StockMovementRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function save(string $businessId, array $data): array;

    public function getMovementsForVariantOption(string $businessId, string $variantOptionExternalId, int $limit = 100): array;
}

interface LocationRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function save(string $businessId, array $data): array;
}