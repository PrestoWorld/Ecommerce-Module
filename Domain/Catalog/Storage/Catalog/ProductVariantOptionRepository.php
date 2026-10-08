<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;

final class ProductVariantOptionRepository extends AbstractRepository implements ProductVariantOptionRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_variant_options')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('product_variant_options')),
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
        $row = $this->db->select('*')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findBySku(string $businessId, string $sku): ?array
    {
        $row = $this->db->select('*')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByVariant(string $businessId, string $variantExternalId): array
    {
        $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
        $variant = $variantRepo->find($businessId, $variantExternalId);
        if (!$variant) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('variant_id', $variant['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('binding_type', 'ASC')
            ->orderBy('condition', 'ASC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findDefaultByVariant(string $businessId, string $variantExternalId): ?array
    {
        $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
        $variant = $variantRepo->find($businessId, $variantExternalId);
        if (!$variant) {
            return null;
        }

        $row = $this->db->select('*')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('variant_id', $variant['id'])
            ->where('is_default', true)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByAttributes(string $businessId, string $variantExternalId, array $attributes): ?array
    {
        $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
        $variant = $variantRepo->find($businessId, $variantExternalId);
        if (!$variant) {
            return null;
        }

        $query = $this->db->select('*')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('variant_id', $variant['id']);

        if (isset($attributes['binding_type'])) {
            $query->where('binding_type', $attributes['binding_type']);
        }
        if (isset($attributes['condition'])) {
            $query->where('condition', $attributes['condition']);
        }
        if (isset($attributes['format'])) {
            $query->where('format', $attributes['format']);
        }
        if (isset($attributes['color'])) {
            $query->where('color', $attributes['color']);
        }
        if (isset($attributes['size'])) {
            $query->where('size', $attributes['size']);
        }

        $row = $query->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'option_' . bin2hex(random_bytes(8));
        }
        $now = time();

        // Resolve variant_id and master_id
        $variantId = $data['variant_id'] ?? null;
        $masterId = $data['master_id'] ?? null;

        if (!$variantId && isset($data['variant_external_id'])) {
            $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
            $variant = $variantRepo->find($businessId, $data['variant_external_id']);
            $variantId = $variant['id'] ?? null;
            $masterId = $variant['master_id'] ?? null;
        }

        if (!$masterId && isset($data['master_external_id'])) {
            $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
            $master = $masterRepo->find($businessId, $data['master_external_id']);
            $masterId = $master['id'] ?? null;
        }

        if (!$variantId || !$masterId) {
            throw new \InvalidArgumentException('variant_id (or variant_external_id) and master_id (or master_external_id) are required');
        }

        // Build JSON fields
        $attributes = isset($data['attributes']) && is_array($data['attributes']) ? json_encode($data['attributes']) : ($data['attributes'] ?? null);
        $images = isset($data['images']) && is_array($data['images']) ? json_encode($data['images']) : ($data['images'] ?? null);

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'variant_id' => $variantId,
            'master_id' => $masterId,
            'sku' => (string) ($data['sku'] ?? ''),
            'barcode' => $data['barcode'] ?? null,
            'binding_type' => $data['binding_type'] ?? $data['bindingType'] ?? null,
            'condition' => (string) ($data['condition'] ?? 'new'),
            'format' => (string) ($data['format'] ?? 'physical'),
            'color' => $data['color'] ?? null,
            'size' => $data['size'] ?? null,
            'attributes' => $attributes,
            'cover_price' => $data['cover_price'] ?? $data['coverPrice'] ?? null,
            'wholesale_price' => $data['wholesale_price'] ?? $data['wholesalePrice'] ?? null,
            'cost_price' => $data['cost_price'] ?? $data['costPrice'] ?? null,
            'currency' => (string) ($data['currency'] ?? 'VND'),
            'stock_on_hand' => (int) ($data['stock_on_hand'] ?? $data['stockOnHand'] ?? 0),
            'stock_reserved' => (int) ($data['stock_reserved'] ?? $data['stockReserved'] ?? 0),
            'stock_available' => (int) ($data['stock_available'] ?? $data['stockAvailable'] ?? (($data['stock_on_hand'] ?? $data['stockOnHand'] ?? 0) - ($data['stock_reserved'] ?? $data['stockReserved'] ?? 0))),
            'reorder_point' => (int) ($data['reorder_point'] ?? $data['reorderPoint'] ?? 0),
            'reorder_qty' => (int) ($data['reorder_qty'] ?? $data['reorderQty'] ?? 0),
            'location' => $data['location'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? $data['supplierId'] ?? null,
            'supplier_sku' => $data['supplier_sku'] ?? $data['supplierSku'] ?? null,
            'weight' => $data['weight'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'cover_image' => $data['cover_image'] ?? $data['coverImage'] ?? null,
            'images' => $images,
            'status' => (string) ($data['status'] ?? 'active'),
            'is_default' => (bool) ($data['is_default'] ?? $data['isDefault'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
        ];

        // If setting as default, unset other defaults for this variant
        if ($values['is_default']) {
            $this->db->update($this->table('product_variant_options'), ['is_default' => false], [
                'business_id' => $businessId,
                'variant_id' => $variantId,
            ])->run();
        }

        $existing = $this->db->select('id')->from($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('product_variant_options'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('product_variant_options'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);
        $values['attributes'] = $this->decodeJson($values['attributes']);
        $values['images'] = $this->decodeJson($values['images']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('product_variant_options'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }

    public function updateStock(string $businessId, string $externalId, int $quantity, string $operation = 'add'): array
    {
        $option = $this->find($businessId, $externalId);
        if (!$option) {
            throw new \InvalidArgumentException("Variant option not found: $externalId");
        }

        $currentOnHand = (int) ($option['stock_on_hand'] ?? 0);
        $currentReserved = (int) ($option['stock_reserved'] ?? 0);

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

        $this->db->update($this->table('product_variant_options'), $values, ['id' => $option['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function reserveStock(string $businessId, string $externalId, int $quantity): array
    {
        $option = $this->find($businessId, $externalId);
        if (!$option) {
            throw new \InvalidArgumentException("Variant option not found: $externalId");
        }

        $currentOnHand = (int) ($option['stock_on_hand'] ?? 0);
        $currentReserved = (int) ($option['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($option['stock_available'] ?? 0);

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

        $this->db->update($this->table('product_variant_options'), $values, ['id' => $option['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function releaseStock(string $businessId, string $externalId, int $quantity): array
    {
        $option = $this->find($businessId, $externalId);
        if (!$option) {
            throw new \InvalidArgumentException("Variant option not found: $externalId");
        }

        $currentReserved = (int) ($option['stock_reserved'] ?? 0);
        $currentAvailable = (int) ($option['stock_available'] ?? 0);

        $newReserved = max(0, $currentReserved - $quantity);
        $newAvailable = $currentAvailable + min($quantity, $currentReserved);

        $values = [
            'stock_reserved' => $newReserved,
            'stock_available' => $newAvailable,
            'updated_at' => time(),
        ];

        $this->db->update($this->table('product_variant_options'), $values, ['id' => $option['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function getChannelProducts(string $businessId, string $optionExternalId): array
    {
        $option = $this->find($businessId, $optionExternalId);
        if (!$option) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('variant_option_id', $option['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['variant_external_id']) && is_string($filters['variant_external_id']) && $filters['variant_external_id'] !== '') {
            $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
            $variant = $variantRepo->find($businessId, $filters['variant_external_id']);
            if ($variant) {
                $query->where('variant_id', $variant['id']);
            }
        }

        if (isset($filters['master_external_id']) && is_string($filters['master_external_id']) && $filters['master_external_id'] !== '') {
            $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
            $master = $masterRepo->find($businessId, $filters['master_external_id']);
            if ($master) {
                $query->where('master_id', $master['id']);
            }
        }

        if (isset($filters['variant_id']) && is_numeric($filters['variant_id'])) {
            $query->where('variant_id', (int) $filters['variant_id']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('sku', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%')
                ->orWhere('barcode', 'LIKE', '%' . $keyword . '%')
                ->orWhere('binding_type', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['binding_type']) && is_string($filters['binding_type']) && $filters['binding_type'] !== '') {
            $query->where('binding_type', $filters['binding_type']);
        }

        if (isset($filters['condition']) && is_string($filters['condition']) && $filters['condition'] !== '') {
            $query->where('condition', $filters['condition']);
        }

        if (isset($filters['format']) && is_string($filters['format']) && $filters['format'] !== '') {
            $query->where('format', $filters['format']);
        }

        if (isset($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('stock_available', '<=', new \Cycle\Database\Query\Expression('reorder_point'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'sort_order';
        $direction = 'ASC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'sku', 'sort_order', 'cover_price', 'stock_available', 'binding_type', 'condition', 'format'], true)) {
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