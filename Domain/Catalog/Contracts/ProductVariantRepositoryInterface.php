<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts;

interface ProductVariantRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findBySku(string $businessId, string $sku): ?array;

    public function findByMaster(string $businessId, string $masterExternalId): array;

    public function findDefaultByMaster(string $businessId, string $masterExternalId): ?array;

    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $externalId): bool;

    public function updateStock(string $businessId, string $externalId, int $quantity, string $operation = 'add'): array;

    public function reserveStock(string $businessId, string $externalId, int $quantity): array;

    public function releaseStock(string $businessId, string $externalId, int $quantity): array;

    public function getChannelProducts(string $businessId, string $variantExternalId): array;
}