<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface OrderRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function save(string $businessId, array $data): array;

    public function sources(string $businessId): array;

    public function nextInternalId(string $businessId): int;
}
