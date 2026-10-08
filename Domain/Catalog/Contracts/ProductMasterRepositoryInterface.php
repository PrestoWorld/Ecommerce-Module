<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts;

interface ProductMasterRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findBySku(string $businessId, string $sku): ?array;

    public function findByIsbn(string $businessId, string $isbn): ?array;

    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $externalId): bool;

    public function getVariants(string $businessId, string $masterExternalId): array;

    public function getChannelProducts(string $businessId, string $masterExternalId): array;
}