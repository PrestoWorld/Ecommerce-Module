<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Pos;

use PrestoWorld\Modules\Ecommerce\Contracts\CustomerRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;
use Cycle\Database\Query\SelectQuery;

final class CustomerRepository extends AbstractRepository implements CustomerRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('customers')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('customers')),
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
        $row = $this->db->select('*')->from($this->table('customers'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? (string) time());
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'code' => (string) ($data['code'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'mobile' => (string) ($data['mobile'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('customers'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('customers'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = (int) ($existing['id'] ?? 0);
            $this->db->update($this->table('customers'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode(is_string($values['payload']) ? $values['payload'] : null);

        return $values;
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('name', 'LIKE', '%' . $keyword . '%')
                ->orWhere('mobile', 'LIKE', '%' . $keyword . '%')
                ->orWhere('email', 'LIKE', '%' . $keyword . '%')
                ->orWhere('code', 'LIKE', '%' . $keyword . '%'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'name', 'mobile'], true)) {
                continue;
            }
            $column = $key;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }
}