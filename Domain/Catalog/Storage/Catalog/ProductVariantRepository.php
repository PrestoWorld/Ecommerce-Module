<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductMasterRepository;

final class ProductVariantRepository extends AbstractRepository implements ProductVariantRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_variants')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_variants')),
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
        $row = $this->db->select('*')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findBySku(string $businessId, string $sku): ?array
    {
        $row = $this->db->select('*')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByMaster(string $businessId, string $masterExternalId): array
    {
        $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
        $master = $masterRepo->find($businessId, $masterExternalId);
        if (!$master) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('master_id', $master['id'])
            ->orderBy('edition', 'ASC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findDefaultByMaster(string $businessId, string $masterExternalId): ?array
    {
        $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
        $master = $masterRepo->find($businessId, $masterExternalId);
        if (!$master) {
            return null;
        }

        $row = $this->db->select('*')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('master_id', $master['id'])
            ->where('is_default', true)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'variant_' . bin2hex(random_bytes(8));
        }
        $now = time();

        // Resolve master_id from master_external_id
        $masterId = $data['master_id'] ?? null;
        if (!$masterId && isset($data['master_external_id'])) {
            $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
            $master = $masterRepo->find($businessId, $data['master_external_id']);
            $masterId = $master['id'] ?? null;
        }

        if (!$masterId) {
            throw new \InvalidArgumentException('master_id or master_external_id is required');
        }

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'master_id' => $masterId,
            'sku' => (string) ($data['sku'] ?? ''),
            'barcode' => $data['barcode'] ?? null,
            'edition' => (int) ($data['edition'] ?? 1),
            'edition_name' => $data['edition_name'] ?? null,
            'publication_year' => $data['publication_year'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'print_run' => $data['print_run'] ?? null,
            'print_date' => $data['print_date'] ?? null,
            'cover_price' => (int) ($data['cover_price'] ?? 0),
            'wholesale_price' => (int) ($data['wholesale_price'] ?? 0),
            'cost_price' => (int) ($data['cost_price'] ?? 0),
            'currency' => (string) ($data['currency'] ?? 'VND'),
            'stock_on_hand' => (int) ($data['stock_on_hand'] ?? 0),
            'stock_reserved' => (int) ($data['stock_reserved'] ?? 0),
            'stock_available' => (int) ($data['stock_available'] ?? (($data['stock_on_hand'] ?? 0) - ($data['stock_reserved'] ?? 0))),
            'reorder_point' => (int) ($data['reorder_point'] ?? 0),
            'reorder_qty' => (int) ($data['reorder_qty'] ?? 0),
            'location' => $data['location'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'supplier_sku' => $data['supplier_sku'] ?? null,
            'weight' => $data['weight'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'status' => (string) ($data['status'] ?? 'active'),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        // If setting as default, unset other defaults for this master
        if ($values['is_default']) {
            $this->db->update($this->table('product_variants'), ['is_default' => false], [
                'business_id' => $businessId,
                'master_id' => $masterId,
            ])->run();
        }

        $existing = $this->db->select('id')->from($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('product_variants'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('product_variants'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('product_variants'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }

    public function updateStock(string $businessId, string $externalId, int $quantity, string $operation = 'add'): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException("Variant not found: $externalId");
        }

        $currentOnHand = (int) ($variant['stock_on_hand'] ?? 0);
        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);

        if ($operation === 'add') {
            $newOnHand = $currentOnHand + $quantity;
        } elseif ($operation === 'subtract') {
            $newOnHand = max(0, $currentOnHand - $quantity);
        } else { // 'set'
            $newOnHand = max(0, $quantity);
        }

        $newAvailable = max(0, $newOnHand - $currentReserved);

        $values = [
            'stock_on_hand' => $newOnHand,
            'stock_available' => $newAvailable,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('product_variants'), $values, ['id' => $variant['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function reserveStock(string $businessId, string $externalId, int $quantity): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException("Variant not found: $externalId");
        }

        $currentOnHand = (int) ($variant['stock_on_hand'] ?? 0);
        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($variant['stock_available'] ?? 0);

        if ($currentAvailable < $quantity) {
            throw new \RuntimeException("Insufficient stock: available $currentAvailable, requested $quantity");
        }

        $newReserved = $currentReserved + $quantity;
        $newAvailable = $currentAvailable - $quantity;

        $values = [
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('product_variants'), $values, ['id' => $variant['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function releaseStock(string $businessId, string $externalId, int $quantity): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException("Variant not found: $externalId");
        }

        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($variant['stock_available'] ?? 0);

        $newReserved = max(0, $currentReserved - $quantity);
        $newAvailable = $currentAvailable + min($quantity, $currentReserved);

        $values = [
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('product_variants'), $values, ['id' => $variant['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function getChannelProducts(string $businessId, string $variantExternalId): array
    {
        $variant = $this->find($businessId, $variantExternalId);
        if (!$variant) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('variant_id', $variant['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['master_external_id']) && is_string($filters['master_external_id']) && $filters['master_external_id'] !== '') {
            $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
            $master = $masterRepo->find($businessId, $filters['master_external_id']);
            if ($master) {
                $query->where('master_id', $master['id']);
            }
        }

        if (isset($filters['master_id']) && is_numeric($filters['master_id'])) {
            $query->where('master_id', (int) $filters['master_id']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('sku', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%')
                ->orWhere('barcode', 'LIKE', '%' . $keyword . '%')
                ->orWhere('edition_name', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['supplier_id']) && is_string($filters['supplier_id']) && $filters['supplier_id'] !== '') {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('stock_available', '<=', new \Cycle\Database\Query\Expression('reorder_point'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'sku', 'edition', 'publication_year', 'cover_price', 'stock_available'], true)) {
                continue;
            }
            $column = $key;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }
}