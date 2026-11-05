<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;

final class ChannelProductRepository extends AbstractRepository implements ChannelProductRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);
        $query = $this->applyFilters($this->db->select('*')->from($this->table('channel_products')), $businessId, $filters);
        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();
        $total = $this->applyFilters($this->db->select('*')->from($this->table('channel_products')), $businessId, $filters)->count();
        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();
        if ($row === false) return null;
        $row = $this->decoded($row);
        $row['images'] = $this->decodeJson($row['images']);
        $row['tags'] = $this->decodeJson($row['tags']);
        $row['attributes'] = $this->decodeJson($row['attributes']);
        $row['payload'] = $this->decode($row['payload']);
        return $row;
    }

    public function findByVariant(string $businessId, string $variantExternalId): array
    {
        $variantRepo = new ProductVariantRepository($this->db, fn ($n) => $this->prefix . $n);
        $variant = $variantRepo->find($businessId, $variantExternalId);
        if (!$variant) return [];
        $rows = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('variant_id', $variant['id'])->run()->fetchAll();
        return $this->rows($rows);
    }

    public function findByVariantOption(string $businessId, string $optionExternalId): array
    {
        $optionRepo = new ProductVariantOptionRepository($this->db, fn ($n) => $this->prefix . $n);
        $option = $optionRepo->find($businessId, $optionExternalId);
        if (!$option) return [];
        $rows = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('variant_option_id', $option['id'])->run()->fetchAll();
        return $this->rows($rows);
    }

    public function findByMaster(string $businessId, string $masterExternalId): array
    {
        $masterRepo = new ProductMasterRepository($this->db, fn ($n) => $this->prefix . $n);
        $master = $masterRepo->find($businessId, $masterExternalId);
        if (!$master) return [];
        $rows = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('master_id', $master['id'])->run()->fetchAll();
        return $this->rows($rows);
    }

    public function findByChannel(string $businessId, string $channel, array $filters = [], int $offset = 0, int $size = 50, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);
        $query = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('channel', $channel);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['publish_status'])) $query->where('publish_status', $filters['publish_status']);
        if (!empty($filters['channel_account_id'])) $query->where('channel_account_id', $filters['channel_account_id']);
        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();
        $total = (clone $query)->count();
        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function findByChannelAndExternalId(string $businessId, string $channel, string $channelProductId): ?array
    {
        $row = $this->db->select('*')->from($this->table('channel_products'))->where('business_id', $businessId)->where('channel', $channel)->where('channel_product_id', $channelProductId)->run()->fetch();
        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') $externalId = 'channel_' . bin2hex(random_bytes(8));
        $now = time();

        $productId = $data['product_id'] ?? null;
        if ($productId && is_string($productId)) {
            // Try to resolve from bow_products
            if ($this->db->hasTable($this->prefix . 'products')) {
                $prow = $this->db->select('id')->from($this->prefix . 'products')
                    ->where('business_id', $businessId)
                    ->where('external_id', $productId)
                    ->run()->fetch();
                if ($prow) {
                    $productId = is_array($prow) ? ($prow['id'] ?? null) : ($prow->id ?? null);
                }
            }
        }

        $variantOptionId = $data['variant_option_id'] ?? null;
        $variantId = $data['variant_id'] ?? null;
        $masterId = $data['master_id'] ?? null;

        if ($variantOptionId && !$variantId && !$masterId && isset($data['variant_option_external_id'])) {
            $optionRepo = new ProductVariantOptionRepository($this->db, fn ($n) => $this->prefix . $n);
            $option = $optionRepo->find($businessId, $data['variant_option_external_id']);
            if ($option) { $variantOptionId = $option['id']; $variantId = $option['variant_id']; $masterId = $option['master_id']; }
        } elseif (!$variantId && isset($data['variant_external_id'])) {
            $variantRepo = new ProductVariantRepository($this->db, fn ($n) => $this->prefix . $n);
            $variant = $variantRepo->find($businessId, $data['variant_external_id']);
            $variantId = $variant['id'] ?? null;
            $masterId = $variant['master_id'] ?? null;
        } elseif (!$masterId && isset($data['master_external_id'])) {
            $masterRepo = new ProductMasterRepository($this->db, fn ($n) => $this->prefix . $n);
            $master = $masterRepo->find($businessId, $data['master_external_id']);
            $masterId = $master['id'] ?? null;
        }
        if (!$variantId || !$masterId) throw new \InvalidArgumentException('variant_id and master_id required');

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'product_id' => $productId,
            'variant_option_id' => $variantOptionId,
            'variant_id' => $variantId,
            'master_id' => $masterId,
            'sku' => (string) ($data['sku'] ?? ''),
            'channel' => (string) ($data['channel'] ?? ''),
            'channel_account_id' => $data['channel_account_id'] ?? null,
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'description_short' => $data['description_short'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'images' => isset($data['images']) && is_array($data['images']) ? json_encode($data['images']) : ($data['images'] ?? null),
            'tags' => isset($data['tags']) && is_array($data['tags']) ? json_encode($data['tags']) : ($data['tags'] ?? null),
            'attributes' => isset($data['attributes']) && is_array($data['attributes']) ? json_encode($data['attributes']) : ($data['attributes'] ?? null),
            'sale_price' => (int) ($data['sale_price'] ?? 0),
            'compare_at_price' => (int) ($data['compare_at_price'] ?? 0),
            'promotion_price' => $data['promotion_price'] ?? null,
            'promotion_start' => $data['promotion_start'] ?? null,
            'promotion_end' => $data['promotion_end'] ?? null,
            'currency' => (string) ($data['currency'] ?? 'VND'),
            'channel_stock' => (int) ($data['channel_stock'] ?? 0),
            'stock_sync_enabled' => (bool) ($data['stock_sync_enabled'] ?? true),
            'last_synced_at' => $data['last_synced_at'] ?? null,
            'channel_product_id' => $data['channel_product_id'] ?? null,
            'channel_variant_id' => $data['channel_variant_id'] ?? null,
            'channel_category_id' => $data['channel_category_id'] ?? null,
            'channel_url' => $data['channel_url'] ?? null,
            'slug' => $data['slug'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status' => (string) ($data['status'] ?? 'draft'),
            'publish_status' => (string) ($data['publish_status'] ?? 'unpublished'),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
            'published_at' => $data['published_at'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('channel_products'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();
        if ($existing === false) {
            $result = $this->db->insert($this->table('channel_products'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('channel_products'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);
        $values['images'] = $this->decodeJson($values['images']);
        $values['tags'] = $this->decodeJson($values['tags']);
        $values['attributes'] = $this->decodeJson($values['attributes']);
        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('channel_products'))->where('business_id', $businessId)->where('external_id', $externalId)->run();
        return $result > 0;
    }

    public function updateStock(string $businessId, string $externalId, int $stock): array
    {
        $values = ['channel_stock' => max(0, $stock), 'last_synced_at' => time(), 'updated_at' => time()];
        $result = $this->db->update($this->table('channel_products'), $values, ['business_id' => $businessId, 'external_id' => $externalId])->run();
        if ($result === 0) throw new \InvalidArgumentException("Channel product not found: $externalId");
        return $this->find($businessId, $externalId) ?? [];
    }

    public function updatePublishStatus(string $businessId, string $externalId, string $status): array
    {
        $valid = ['unpublished', 'published', 'pending', 'failed', 'rejected'];
        if (!in_array($status, $valid, true)) throw new \InvalidArgumentException("Invalid publish_status: $status");
        $values = ['publish_status' => $status, 'updated_at' => time()];
        if ($status === 'published') { $values['published_at'] = time(); $values['status'] = 'active'; }
        $result = $this->db->update($this->table('channel_products'), $values, ['business_id' => $businessId, 'external_id' => $externalId])->run();
        if ($result === 0) throw new \InvalidArgumentException("Channel product not found: $externalId");
        return $this->find($businessId, $externalId) ?? [];
    }

    public function syncToChannel(string $businessId, string $externalId, array $channelData): array
    {
        $channelProduct = $this->find($businessId, $externalId);
        if (!$channelProduct) throw new \InvalidArgumentException("Channel product not found: $externalId");
        $values = [
            'channel_product_id' => $channelData['channel_product_id'] ?? $channelProduct['channel_product_id'],
            'channel_variant_id' => $channelData['channel_variant_id'] ?? $channelProduct['channel_variant_id'],
            'channel_category_id' => $channelData['channel_category_id'] ?? $channelProduct['channel_category_id'],
            'channel_url' => $channelData['channel_url'] ?? $channelProduct['channel_url'],
            'channel_stock' => $channelData['channel_stock'] ?? $channelProduct['channel_stock'],
            'last_synced_at' => time(),
            'updated_at' => time(),
            'payload' => $this->encode(array_merge($this->decode($channelProduct['payload'] ?? '{}'), ['last_sync' => $channelData])),
        ];
        $this->db->update($this->table('channel_products'), $values, ['id' => $channelProduct['id']])->run();
        return $this->find($businessId, $externalId) ?? [];
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);
        if (!empty($filters['variant_option_external_id'])) {
            $optionRepo = new ProductVariantOptionRepository($this->db, fn ($n) => $this->prefix . $n);
            $option = $optionRepo->find($businessId, $filters['variant_option_external_id']);
            if ($option) $query->where('variant_option_id', $option['id']);
        }
        if (!empty($filters['variant_external_id'])) {
            $variantRepo = new ProductVariantRepository($this->db, fn ($n) => $this->prefix . $n);
            $variant = $variantRepo->find($businessId, $filters['variant_external_id']);
            if ($variant) $query->where('variant_id', $variant['id']);
        }
        if (!empty($filters['master_external_id'])) {
            $masterRepo = new ProductMasterRepository($this->db, fn ($n) => $this->prefix . $n);
            $master = $masterRepo->find($businessId, $filters['master_external_id']);
            if ($master) $query->where('master_id', $master['id']);
        }
        if (!empty($filters['channel'])) $query->where('channel', $filters['channel']);
        if (!empty($filters['channel_account_id'])) $query->where('channel_account_id', $filters['channel_account_id']);
        if (!empty($filters['keyword'])) $query->where(fn ($q) => $q->where('sku', 'LIKE', '%'.$filters['keyword'].'%')->orWhere('title', 'LIKE', '%'.$filters['keyword'].'%'));
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['publish_status'])) $query->where('publish_status', $filters['publish_status']);
        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at'; $direction = 'DESC';
        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'sku', 'sale_price', 'channel', 'published_at'], true)) continue;
            $column = $key; $direction = strtolower((string)$value) === 'asc' ? 'ASC' : 'DESC'; break;
        }
        return [$column, $direction];
    }
}