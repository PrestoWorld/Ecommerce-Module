<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\NhanhApiReplacement\Server\Contracts\BillRepositoryInterface;

final class BillRepository extends AbstractRepository implements BillRepositoryInterface
{
    private const SORTABLE = ['created_at', 'id', 'code', 'status', 'total_amount'];

    public function list(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
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

    public function deleted(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $items = $this->db->select('*')->from($this->table('bills'))
            ->where('business_id', $businessId)
            ->where('status', 0)
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();

        return ['items' => $this->rows($items), 'total' => count($items)];
    }

    public function retail(string $businessId, array $data): array
    {
        $now = time();
        $items = $data['items'] ?? [];
        $totalAmount = 0;

        foreach ($items as $item) {
            $totalAmount += (int) ($item['amount'] ?? 0);
        }

        $values = [
            'business_id' => $businessId,
            'external_id' => (string) ($data['external_id'] ?? ''),
            'code' => (string) ($data['code'] ?? ''),
            'type' => (int) ($data['type'] ?? 1),
            'status' => (int) ($data['status'] ?? 1),
            'customer_id' => (int) ($data['customer_id'] ?? 0),
            'customer_name' => (string) ($data['customer_name'] ?? ''),
            'customer_mobile' => (string) ($data['customer_mobile'] ?? ''),
            'total_amount' => $totalAmount,
            'note' => (string) ($data['note'] ?? ''),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
            'payload' => $this->encode($data),
        ];

        $result = $this->db->insert($this->table('bills'))->values($values)->run();
        $billId = (int) $result;

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $price = (int) ($item['price'] ?? 0);
            $this->db->insert($this->table('bill_items'))->values([
                'bill_id' => $billId,
                'product_id' => (int) ($item['product_id'] ?? 0),
                'product_code' => (string) ($item['product_code'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'quantity' => $qty,
                'price' => $price,
                'amount' => $qty * $price,
                'unit' => (string) ($item['unit'] ?? ''),
                'created_at' => (int) ($item['created_at'] ?? $now),
                'payload' => $this->encode($item),
            ])->run();
        }

        $values['id'] = $billId;
        $values['payload'] = $this->decode($values['payload']);

        return $values;
    }

    public function addRetail(string $businessId, array $data): array
    {
        return $this->retail($businessId, $data);
    }

    public function imexs(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
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

    public function draftList(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $items = $this->baseDraftsQuery($businessId, $filters)
            ->orderBy($column, $direction)
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();

        $total = $this->totalDrafts($businessId, $filters);

        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function draftProducts(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        return ['items' => [], 'total' => 0];
    }

    private function baseQuery(string $businessId, array $filters): SelectQuery
    {
        return $this->applyFilters(
            $this->db->select('*')->from($this->table('bills')),
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
                ->where('code', 'LIKE', '%' . $keyword . '%')
                ->orWhere('customer_name', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && is_numeric($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['type']) && is_numeric($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        return $query;
    }

    private function total(string $businessId, array $filters): int
    {
        return $this->applyFilters(
            $this->db->select('*')->from($this->table('bills')),
            $businessId,
            $filters,
        )->count();
    }

    private function baseDraftsQuery(string $businessId, array $filters): SelectQuery
    {
        return $this->applyDraftsFilters(
            $this->db->select('*')->from($this->table('draft_bills')),
            $businessId,
            $filters,
        );
    }

    private function applyDraftsFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_numeric($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        return $query;
    }

    private function totalDrafts(string $businessId, array $filters): int
    {
        return $this->applyDraftsFilters(
            $this->db->select('*')->from($this->table('draft_bills')),
            $businessId,
            $filters,
        )->count();
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $normalized = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($normalized, self::SORTABLE, true)) {
                continue;
            }
            $column = $normalized;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }
}
