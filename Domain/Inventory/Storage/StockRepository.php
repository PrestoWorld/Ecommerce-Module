<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class StockRepository extends AbstractRepository implements StockRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('stock_items')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('stock_items')),
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
        $row = $this->db->select('*')->from($this->table('stock_items'))
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

    public function findByVariantOption(string $businessId, string $variantOptionExternalId): array
    {
        $rows = $this->db->select('*')->from($this->table('stock_items'))
            ->where('business_id', $businessId)
            ->where('variant_option_external_id', $variantOptionExternalId)
            ->orderBy('location_code', 'ASC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findByLocation(string $businessId, string $locationId): array
    {
        $rows = $this->db->select('*')->from($this->table('stock_items'))
            ->where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function getAggregateStock(string $businessId, string $variantOptionExternalId): array
    {
        $rows = $this->db->select(
            'SUM(quantity_on_hand) as total_on_hand',
            'SUM(quantity_reserved) as total_reserved',
            'SUM(quantity_available) as total_available',
            'COUNT(*) as location_count'
        )->from($this->table('stock_items'))
            ->where('business_id', $businessId)
            ->where('variant_option_external_id', $variantOptionExternalId)
            ->run()->fetch();

        return $rows === false ? [
            'total_on_hand' => 0,
            'total_reserved' => 0,
            'total_available' => 0,
            'location_count' => 0,
        ] : (array) $rows;
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'stock_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'variant_option_external_id' => (string) ($data['variant_option_external_id'] ?? $data['variantOptionExternalId'] ?? ''),
            'location_id' => (string) ($data['location_id'] ?? $data['locationId'] ?? ''),
            'location_code' => (string) ($data['location_code'] ?? $data['locationCode'] ?? ''),
            'quantity_on_hand' => (int) ($data['quantity_on_hand'] ?? $data['quantityOnHand'] ?? 0),
            'quantity_reserved' => (int) ($data['quantity_reserved'] ?? $data['quantityReserved'] ?? 0),
            'quantity_available' => (int) ($data['quantity_available'] ?? $data['quantityAvailable'] ?? (($data['quantity_on_hand'] ?? $data['quantityOnHand'] ?? 0) - ($data['quantity_reserved'] ?? $data['quantityReserved'] ?? 0))),
            'reorder_point' => (int) ($data['reorder_point'] ?? $data['reorderPoint'] ?? 0),
            'reorder_quantity' => (int) ($data['reorder_quantity'] ?? $data['reorderQuantity'] ?? 0),
            'cost_price' => $data['cost_price'] ?? $data['costPrice'] ?? null,
            'average_cost' => $data['average_cost'] ?? $data['averageCost'] ?? null,
            'status' => (string) ($data['status'] ?? 'active'),
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
            'last_counted_at' => $data['last_counted_at'] ?? $data['lastCountedAt'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('stock_items'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('stock_items'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('stock_items'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function adjustStock(string $businessId, string $externalId, int $quantity, string $operation, string $reason, ?string $referenceId = null): array
    {
        $stock = $this->find($businessId, $externalId);
        if (!$stock) {
            throw new \InvalidArgumentException("Stock item not found: $externalId");
        }

        $currentOnHand = (int) ($stock['quantity_on_hand'] ?? 0);
        $currentReserved = (int) ($stock['quantity_reserved'] ?? 0);

        if ($operation === 'add') {
            $newOnHand = $currentOnHand + $quantity;
        } elseif ($operation === 'subtract') {
            $newOnHand = max(0, $currentOnHand - $quantity);
        } else { // 'set'
            $newOnHand = max(0, $quantity);
        }

        $newAvailable = max(0, $newOnHand - $currentReserved);
        $newStatus = $this->calculateStatus($newAvailable, (int) ($stock['reorder_point'] ?? 0));

        $values = [
            'quantity_on_hand' => $newOnHand,
            'quantity_available' => $newAvailable,
            'status' => $newStatus,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('stock_items'), $values, ['id' => $stock['id']])->run();

        // Record movement
        $this->recordMovement($businessId, [
            'stock_item_external_id' => $externalId,
            'variant_option_external_id' => $stock['variant_option_external_id'],
            'location_id' => $stock['location_id'],
            'type' => 'adjustment',
            'quantity_change' => $newOnHand - $currentOnHand,
            'quantity_before' => $currentOnHand,
            'quantity_after' => $newOnHand,
            'reason' => $reason,
            'reference_id' => $referenceId,
            'reference_type' => 'manual',
        ]);

        return $this->find($businessId, $externalId) ?? [];
    }

    public function reserveStock(string $businessId, string $variantOptionExternalId, string $locationId, int $quantity, string $reservationId): array
    {
        $stock = $this->findByVariantOptionAndLocation($businessId, $variantOptionExternalId, $locationId);
        if (!$stock) {
            throw new \InvalidArgumentException("Stock not found for variant option $variantOptionExternalId at location $locationId");
        }

        $currentAvailable = (int) ($stock['quantity_available'] ?? 0);
        if ($currentAvailable < $quantity) {
            throw new \RuntimeException("Insufficient stock: available $currentAvailable, requested $quantity");
        }

        $newReserved = (int) ($stock['quantity_reserved'] ?? 0) + $quantity;
        $newAvailable = $currentAvailable - $quantity;
        $newStatus = $this->calculateStatus($newAvailable, (int) ($stock['reorder_point'] ?? 0));

        $values = [
            'quantity_reserved' => $newReserved,
            'quantity_available' => $newAvailable,
            'status' => $newStatus,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('stock_items'), $values, ['id' => $stock['id']])->run();

        // Record movement
        $this->recordMovement($businessId, [
            'stock_item_external_id' => $stock['external_id'],
            'variant_option_external_id' => $variantOptionExternalId,
            'location_id' => $locationId,
            'type' => 'reservation',
            'quantity_change' => -$quantity,
            'quantity_before' => $currentAvailable + $quantity,
            'quantity_after' => $newAvailable,
            'reason' => "Reserved for order/reservation: $reservationId",
            'reference_id' => $reservationId,
            'reference_type' => 'reservation',
        ]);

        return $this->find($businessId, $stock['external_id']) ?? [];
    }

    public function releaseStock(string $businessId, string $variantOptionExternalId, string $locationId, int $quantity, string $reservationId, string $reason): array
    {
        $stock = $this->findByVariantOptionAndLocation($businessId, $variantOptionExternalId, $locationId);
        if (!$stock) {
            throw new \InvalidArgumentException("Stock not found for variant option $variantOptionExternalId at location $locationId");
        }

        $currentReserved = (int) ($stock['quantity_reserved'] ?? 0);
        $currentAvailable = (int) ($stock['quantity_available'] ?? 0);

        $releaseQty = min($quantity, $currentReserved);
        $newReserved = $currentReserved - $releaseQty;
        $newAvailable = $currentAvailable + $releaseQty;
        $newStatus = $this->calculateStatus($newAvailable, (int) ($stock['reorder_point'] ?? 0));

        $values = [
            'quantity_reserved' => $newReserved,
            'quantity_available' => $newAvailable,
            'status' => $newStatus,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('stock_items'), $values, ['id' => $stock['id']])->run();

        // Record movement
        $this->recordMovement($businessId, [
            'stock_item_external_id' => $stock['external_id'],
            'variant_option_external_id' => $variantOptionExternalId,
            'location_id' => $locationId,
            'type' => 'release',
            'quantity_change' => $releaseQty,
            'quantity_before' => $currentAvailable,
            'quantity_after' => $newAvailable,
            'reason' => $reason,
            'reference_id' => $reservationId,
            'reference_type' => 'reservation',
        ]);

        return $this->find($businessId, $stock['external_id']) ?? [];
    }

    public function transferStock(string $businessId, string $variantOptionExternalId, string $fromLocationId, string $toLocationId, int $quantity, string $reason): array
    {
        // Out from source
        $fromStock = $this->findByVariantOptionAndLocation($businessId, $variantOptionExternalId, $fromLocationId);
        if (!$fromStock) {
            throw new \InvalidArgumentException("Source stock not found");
        }

        $fromAvailable = (int) ($fromStock['quantity_available'] ?? 0);
        if ($fromAvailable < $quantity) {
            throw new \RuntimeException("Insufficient stock at source location: available $fromAvailable");
        }

        $this->adjustStock($businessId, $fromStock['external_id'], $quantity, 'subtract', "Transfer to $toLocationId: $reason", "transfer:$toLocationId");

        // In to destination
        $toStock = $this->findByVariantOptionAndLocation($businessId, $variantOptionExternalId, $toLocationId);
        if (!$toStock) {
            // Create if not exists
            $locationRepo = new LocationRepository($this->db, $this->prefix);
            $location = $locationRepo->find($businessId, $toLocationId);
            if (!$location) {
                throw new \InvalidArgumentException("Destination location not found");
            }

            $toStock = $this->save($businessId, [
                'variant_option_external_id' => $variantOptionExternalId,
                'location_id' => $toLocationId,
                'location_code' => $location['code'],
                'quantity_on_hand' => 0,
            ]);
        }

        $this->adjustStock($businessId, $toStock['external_id'], $quantity, 'add', "Transfer from $fromLocationId: $reason", "transfer:$fromLocationId");

        return [
            'from' => $this->find($businessId, $fromStock['external_id']),
            'to' => $this->find($businessId, $toStock['external_id']),
        ];
    }

    private function findByVariantOptionAndLocation(string $businessId, string $variantOptionExternalId, string $locationId): ?array
    {
        $row = $this->db->select('*')->from($this->table('stock_items'))
            ->where('business_id', $businessId)
            ->where('variant_option_external_id', $variantOptionExternalId)
            ->where('location_id', $locationId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    private function calculateStatus(int $available, int $reorderPoint): string
    {
        if ($available <= 0) return 'out_of_stock';
        if ($available <= $reorderPoint && $reorderPoint > 0) return 'low_stock';
        return 'active';
    }

    private function recordMovement(string $businessId, array $data): void
    {
        $externalId = 'mov_' . bin2hex(random_bytes(8));
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'stock_item_external_id' => $data['stock_item_external_id'],
            'variant_option_external_id' => $data['variant_option_external_id'],
            'location_id' => $data['location_id'],
            'type' => $data['type'],
            'quantity_change' => $data['quantity_change'],
            'quantity_before' => $data['quantity_before'],
            'quantity_after' => $data['quantity_after'],
            'reason' => $data['reason'],
            'reference_id' => $data['reference_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => $now,
        ];

        $this->db->insert($this->table('stock_movements'))->values($values)->run();
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

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('quantity_available', '<=', new \Cycle\Database\Query\Expression('reorder_point'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'quantity_on_hand', 'quantity_available', 'updated_at'], true)) {
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