<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Pos;

use PrestoWorld\Modules\Ecommerce\Contracts\OrderRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;
use Cycle\Database\Query\SelectQuery;

final class OrderRepository extends AbstractRepository implements OrderRepositoryInterface
{
    private const SORTABLE = ['created_at', 'id', 'updated_at', 'total_amount'];

    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $items = $this->baseQuery($businessId, $filters)
            ->orderBy($column, $direction)
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();

        $total = $this->total($businessId, $filters);

        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? $this->nextInternalId($businessId));
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'code' => isset($data['code']) ? (string) $data['code'] : $externalId,
            'status' => (string) ($data['status'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
            'customer_name' => (string) ($data['customer_name'] ?? ''),
            'customer_mobile' => (string) ($data['customer_mobile'] ?? ''),
            'total_amount' => (int) ($data['total_amount'] ?? 0),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('orders'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('orders'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);

        return $values;
    }

    public function sources(string $businessId): array
    {
        $rows = $this->db->select('source')->from($this->table('orders'))
            ->where('business_id', $businessId)
            ->where('source', '!=', '')
            ->distinct()
            ->run()->fetchAll();

        return array_values(array_filter(array_map(
            fn (array $row) => (string) ($row['source'] ?? ''),
            $rows,
        )));
    }

    public function nextInternalId(string $businessId): int
    {
        $max = $this->db->select('id')->from($this->table('orders'))->max('id');

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    private function baseQuery(string $businessId, array $filters): SelectQuery
    {
        return $this->applyFilters(
            $this->db->select('*')->from($this->table('orders')),
            $businessId,
            $filters,
        );
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('customer_name', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%')
                ->orWhere('code', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (string) $filters['status']);
        }

        if (isset($filters['source']) && is_string($filters['source']) && $filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['createdAtFrom']) && is_numeric($filters['createdAtFrom'])) {
            $query->where('created_at', '>=', (int) $filters['createdAtFrom']);
        }
        if (isset($filters['createdAtTo']) && is_numeric($filters['createdAtTo'])) {
            $query->where('created_at', '<=', (int) $filters['createdAtTo']);
        }
        if (isset($filters['updatedAtFrom']) && is_numeric($filters['updatedAtFrom'])) {
            $query->where('updated_at', '>=', (int) $filters['updatedAtFrom']);
        }
        if (isset($filters['updatedAtTo']) && is_numeric($filters['updatedAtTo'])) {
            $query->where('updated_at', '<=', (int) $filters['updatedAtTo']);
        }

        return $query;
    }

    private function total(string $businessId, array $filters): int
    {
        return $this->applyFilters(
            $this->db->select('*')->from($this->table('orders')),
            $businessId,
            $filters,
        )->count();
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $column = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($column, self::SORTABLE, true)) {
                continue;
            }
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }
}