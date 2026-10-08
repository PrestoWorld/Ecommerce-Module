<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Tax\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Contracts\TaxRateRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class TaxRateRepository extends AbstractRepository implements TaxRateRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('tax_rates')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('tax_rates')),
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
        $row = $this->db->select('*')->from($this->table('tax_rates'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = $this->decoded($row);
        $row['applicable_categories'] = $this->decodeJson($row['applicable_categories'] ?? '[]');
        $row['exempt_categories'] = $this->decodeJson($row['exempt_categories'] ?? '[]');
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? '{}');

        return $row;
    }

    public function findByCode(string $businessId, string $code): ?array
    {
        $row = $this->db->select('*')->from($this->table('tax_rates'))
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function getActiveRates(string $businessId, int $timestamp = 0): array
    {
        $timestamp = $timestamp ?: time();

        $rows = $this->db->select('*')->from($this->table('tax_rates'))
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $timestamp)
            ->where(fn (SelectQuery $q) => $q
                ->where('valid_until', '>=', $timestamp)
                ->orWhere('valid_until', null)
            )
            ->orderBy('priority', 'ASC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function getRatesForCategory(string $businessId, string $category, int $timestamp = 0): array
    {
        $allRates = $this->getActiveRates($businessId, $timestamp);

        return array_filter($allRates, function ($rate) use ($category) {
            $applicable = $rate['applicable_categories'] ?? [];
            $exempt = $rate['exempt_categories'] ?? [];

            if (in_array($category, $exempt, true)) {
                return false;
            }

            if (empty($applicable)) {
                return true;
            }

            return in_array($category, $applicable, true);
        });
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'tax_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'name' => (string) ($data['name'] ?? ''),
            'code' => (string) ($data['code'] ?? ''),
            'rate' => (float) ($data['rate'] ?? 0),
            'type' => (string) ($data['type'] ?? 'percentage'),
            'scope' => (string) ($data['scope'] ?? 'national'),
            'region' => $data['region'] ?? null,
            'applicable_categories' => json_encode($data['applicable_categories'] ?? $data['applicableCategories'] ?? []),
            'exempt_categories' => json_encode($data['exempt_categories'] ?? $data['exemptCategories'] ?? []),
            'is_compound' => (bool) ($data['is_compound'] ?? $data['isCompound'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            'valid_from' => (int) ($data['valid_from'] ?? $data['validFrom'] ?? $now),
            'valid_until' => $data['valid_until'] ?? $data['validUntil'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('tax_rates'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('tax_rates'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('tax_rates'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['applicable_categories'] = $this->decodeJson($values['applicable_categories']);
        $values['exempt_categories'] = $this->decodeJson($values['exempt_categories']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('tax_rates'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['code']) && is_string($filters['code']) && $filters['code'] !== '') {
            $query->where('code', $filters['code']);
        }

        if (isset($filters['is_active']) && filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        if (isset($filters['scope']) && is_string($filters['scope']) && $filters['scope'] !== '') {
            $query->where('scope', $filters['scope']);
        }

        if (isset($filters['region']) && is_string($filters['region']) && $filters['region'] !== '') {
            $query->where('region', $filters['region']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('name', 'LIKE', '%' . $keyword . '%')
                ->where('code', 'LIKE', '%' . $keyword . '%'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'priority';
        $direction = 'ASC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['priority', 'rate', 'code', 'created_at', 'id'], true)) {
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