<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts;

interface ChannelProductRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByVariant(string $businessId, string $variantExternalId): array;

    public function findByVariantOption(string $businessId, string $optionExternalId): array;

    public function findByMaster(string $businessId, string $masterExternalId): array;

    public function findByProduct(string $businessId, string $productExternalId): array;

    public function findByChannel(string $businessId, string $channel, array $filters = [], int $offset = 0, int $size = 50, array $sort = []): array;

    public function findByChannelAndExternalId(string $businessId, string $channel, string $channelProductId): ?array;

    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $externalId): bool;
}
