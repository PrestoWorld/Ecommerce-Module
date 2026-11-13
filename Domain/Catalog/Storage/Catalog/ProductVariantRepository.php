<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;

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
        $masterRepo = new ProductMasterRepository($this->db, $this->prefix);
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
        $variants = $this->findByMaster($businessId, $masterExternalId);
        foreach ($variants as $variant) {
            if (!empty($variant['is_default'])) {
                return $variant;
            }
        }

        return $variants[0] ?? null;
    }

    public function updateStock(string $businessId, string $externalId, int $quantity, string $operation = 'add'): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException('Variant not found');
        }

        $currentStock = (int) ($variant['stock_on_hand'] ?? 0);
        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($variant['stock_available'] ?? 0);

        if ($operation === 'add') {
            $newStock = $currentStock + $quantity;
            $newAvailable = $currentAvailable + $quantity;
        } elseif ($operation === 'subtract') {
            $newStock = max(0, $currentStock - $quantity);
            $newAvailable = max(0, $currentAvailable - $quantity);
        } else {
            $newStock = $quantity;
            $newAvailable = $quantity - $currentReserved;
        }

        $now = time();
        $this->db->update($this->table('product_variants'), [
            'stock_on_hand' => $newStock,
            'stock_available' => $newAvailable,
            'updated_at' => $now,
        ], ['business_id' => $businessId, 'external_id' => $externalId])->run();

        return [
            'stock_on_hand' => $newStock,
            'stock_reserved' => $currentReserved,
            'stock_available' => $newAvailable,
        ];
    }

    public function reserveStock(string $businessId, string $externalId, int $quantity): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException('Variant not found');
        }

        $currentAvailable = (int) ($variant['stock_available'] ?? 0);
        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);

        if ($currentAvailable < $quantity) {
            throw new \RuntimeException('Insufficient stock available for reservation');
        }

        $newAvailable = $currentAvailable - $quantity;
        $newReserved = $currentReserved + $quantity;
        $now = time();

        $this->db->update($this->table('product_variants'), [
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
            'updated_at' => $now,
        ], ['business_id' => $businessId, 'external_id' => $externalId])->run();

        return [
            'stock_on_hand' => (int) ($variant['stock_on_hand'] ?? 0),
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
        ];
    }

    public function releaseStock(string $businessId, string $externalId, int $quantity): array
    {
        $variant = $this->find($businessId, $externalId);
        if (!$variant) {
            throw new \InvalidArgumentException('Variant not found');
        }

        $currentReserved = (int) ($variant['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($variant['stock_available'] ?? 0);

        if ($currentReserved < $quantity) {
            throw new \RuntimeException('Cannot release more stock than reserved');
        }

        $newReserved = $currentReserved - $quantity;
        $newAvailable = $currentAvailable + $quantity;
        $now = time();

        $this->db->update($this->table('product_variants'), [
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
            'updated_at' => $now,
        ], ['business_id' => $businessId, 'external_id' => $externalId])->run();

        return [
            'stock_on_hand' => (int) ($variant['stock_on_hand'] ?? 0),
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
        ];
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'var_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $masterRepo = new ProductMasterRepository($this->db, $this->prefix);
        $masterExternal = (string) ($data['master_external_id'] ?? '');
        $masterId = $data['master_id'] ?? null;
        if (!$masterId && $masterExternal !== '') {
            $master = $masterRepo->find($businessId, $masterExternal);
            $masterId = $master['id'] ?? null;
        }
        if (!$masterId) {
            throw new \InvalidArgumentException('master_id required');
        }

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'master_id' => (int) $masterId,
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
            'stock_available' => (int) ($data['stock_available'] ?? 0),
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
}
