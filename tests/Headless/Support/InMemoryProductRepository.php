<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Headless\Support;

use PrestoWorld\Modules\Ecommerce\Contracts\ProductRepositoryInterface;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, array<string, mixed>> keyed by external_id */
    public array $items = [];

    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        $rows = array_values($this->items);

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $needle = strtolower($filters['keyword']);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_contains(strtolower((string) ($row['name'] ?? '')), $needle),
            ));
        }

        $total = count($rows);
        $rows = array_slice($rows, $offset, $size);

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
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        $this->items[$externalId] = $data + ['external_id' => $externalId];

        return ['business_id' => $businessId, 'external_id' => $externalId, 'payload' => $this->items[$externalId]];
    }
}