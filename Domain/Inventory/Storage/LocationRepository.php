<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\LocationRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class LocationRepository extends AbstractRepository implements LocationRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('locations')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('locations')),
            $businessId,
            $filters,
        )->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('locations'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'loc_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'code' => (string) ($data['code'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'type' => (string) ($data['type'] ?? 'warehouse'), // warehouse, store, dropship, virtual
            'address' => isset($data['address']) ? json_encode($data['address']) : null,
            'contact_name' => $data['contact_name'] ?? $data['contactName'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? $data['contactPhone'] ?? null,
            'contact_email' => $data['contact_email'] ?? $data['contactEmail'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            'is_default' => (bool) ($data['is_default'] ?? $data['isDefault'] ?? false),
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
        ];

        if ($values['is_default']) {
            $this->db->update($this->table('locations'), ['is_default' => false], ['business_id' => $businessId])->run();
        }

        $existing = $this->db->select('id')->from($this->table('locations'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('locations'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('locations'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['address'] = $this->decodeJson($values['address']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('code', 'LIKE', '%' . $keyword . '%')
                ->where('name', 'LIKE', '%' . $keyword . '%'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'code', 'name', 'type'], true)) {
                continue;
            }
            $column = $key;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }

    private function decodeJson(?string $value): array
    {
        if (!$value) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}