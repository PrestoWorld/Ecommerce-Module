<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface ShipmentRepositoryInterface
{
    public function create(string $businessId, array $data): array;

    public function find(string $businessId, int $orderId): ?array;

    public function update(string $businessId, int $orderId, array $fields): void;
}
