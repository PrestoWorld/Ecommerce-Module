<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Headless\Support;

use PrestoWorld\Modules\Ecommerce\Contracts\OrderRepositoryInterface;

final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, array<string, mixed>> keyed by external_id */
    public array $items = [];

    public int $next = 1;

    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        $rows = array_slice(array_values($this->items), $offset, $size);
        $total = count($this->items);

        return [
            'items' => array_map(static fn (array $row): array => ['payload' => $row], $rows),
            'total' => $total,
        ];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->items[$externalId] ?? null;

        return $row === null ? null : ['business_id' => $businessId, 'external_id' => $externalId, 'payload' => $row];
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? $this->nextInternalId($businessId));
        $this->items[$externalId] = $data + ['external_id' => $externalId];

        return ['business_id' => $businessId, 'external_id' => $externalId, 'payload' => $this->items[$externalId]];
    }

    public function sources(string $businessId): array
    {
        return [];
    }

    public function nextInternalId(string $businessId): int
    {
        return $this->next++;
    }
}