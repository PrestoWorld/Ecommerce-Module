<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\NhanhApiReplacement\Server\Contracts\EcomRepositoryInterface;

final class EcomRepository extends AbstractRepository implements EcomRepositoryInterface
{
    private const SORTABLE_SHOP = ['created_at', 'id', 'name', 'shop_id', 'channel'];
    private const SORTABLE_RETURN = ['created_at', 'id', 'external_id', 'order_id', 'status'];

    public function shop(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort, self::SORTABLE_SHOP);

        $items = $this->baseShopQuery($businessId, $filters)
            ->orderBy($column, $direction)
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();

        $total = $this->totalShop($businessId, $filters);

        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function returnList(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort, self::SORTABLE_RETURN);

        $items = $this->baseReturnQuery($businessId, $filters)
            ->orderBy($column, $direction)
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();

        $total = $this->totalReturn($businessId, $filters);

        return ['items' => $this->rows($items), 'total' => $total];
    }

    private function baseShopQuery(string $businessId, array $filters): SelectQuery
    {
        return $this->applyShopFilters(
            $this->db->select('*')->from($this->table('ecom_shops')),
            $businessId,
            $filters,
        );
    }

    private function applyShopFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_numeric($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['channel']) && is_string($filters['channel']) && $filters['channel'] !== '') {
            $query->where('channel', $filters['channel']);
        }

        return $query;
    }

    private function totalShop(string $businessId, array $filters): int
    {
        return $this->applyShopFilters(
            $this->db->select('*')->from($this->table('ecom_shops')),
            $businessId,
            $filters,
        )->count();
    }

    private function baseReturnQuery(string $businessId, array $filters): SelectQuery
    {
        return $this->applyReturnFilters(
            $this->db->select('*')->from($this->table('ecom_returns')),
            $businessId,
            $filters,
        );
    }

    private function applyReturnFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_numeric($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        return $query;
    }

    private function totalReturn(string $businessId, array $filters): int
    {
        return $this->applyReturnFilters(
            $this->db->select('*')->from($this->table('ecom_returns')),
            $businessId,
            $filters,
        )->count();
    }

    private function ordering(array $sort, array $allowed): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $normalized = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($normalized, $allowed, true)) {
                continue;
            }
            $column = $normalized;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }
}
