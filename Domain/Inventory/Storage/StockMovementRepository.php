<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockMovementRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class StockMovementRepository extends AbstractRepository implements StockMovementRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('stock_movements')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('stock_movements')),
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
        $row = $this->db->select('*')->from($this->table('stock_movements'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = $this->decoded($row);
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? '{}');

        return $row;
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'mov_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'stock_item_external_id' => (string) ($data['stock_item_external_id'] ?? $data['stockItemExternalId'] ?? ''),
            'variant_option_external_id' => (string) ($data['variant_option_external_id'] ?? $data['variantOptionExternalId'] ?? ''),
            'location_id' => (string) ($data['location_id'] ?? $data['locationId'] ?? ''),
            'type' => (string) ($data['type'] ?? ''), // adjustment, reservation, release, transfer_in, transfer_out, receipt, count
            'quantity_change' => (int) ($data['quantity_change'] ?? $data['quantityChange'] ?? 0),
            'quantity_before' => (int) ($data['quantity_before'] ?? $data['quantityBefore'] ?? 0),
            'quantity_after' => (int) ($data['quantity_after'] ?? $data['quantityAfter'] ?? 0),
            'reason' => (string) ($data['reason'] ?? ''),
            'reference_id' => $data['reference_id'] ?? $data['referenceId'] ?? null,
            'reference_type' => $data['reference_type'] ?? $data['referenceType'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
        ];

        $result = $this->db->insert($this->table('stock_movements'))->values($values)->run();
        $values['id'] = (int) $result;
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function getMovementsForVariantOption(string $businessId, string $variantOptionExternalId, int $limit = 100): array
    {
        $rows = $this->db->select('*')->from($this->table('stock_movements'))
            ->where('business_id', $businessId)
            ->where('variant_option_external_id', $variantOptionExternalId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['variant_option_external_id']) && is_string($filters['variant_option_external_id']) && $filters['variant_option_external_id'] !== '') {
            $query->where('variant_option_external_id', $filters['variant_option_external_id']);
        }

        if (isset($filters['location_id']) && is_string($filters['location_id']) && $filters['location_id'] !== '') {
            $query->where('location_id', $filters['location_id']);
        }

        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['reference_id']) && is_string($filters['reference_id']) && $filters['reference_id'] !== '') {
            $query->where('reference_id', $filters['reference_id']);
        }

        if (isset($filters['date_from']) && is_numeric($filters['date_from'])) {
            $query->where('created_at', '>=', (int) $filters['date_from']);
        }

        if (isset($filters['date_to']) && is_numeric($filters['date_to'])) {
            $query->where('created_at', '<=', (int) $filters['date_to']);
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'quantity_change', 'type'], true)) {
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