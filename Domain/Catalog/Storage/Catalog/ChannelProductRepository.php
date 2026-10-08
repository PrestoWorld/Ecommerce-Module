<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\AbstractRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductMasterRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductVariantOptionRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductVariantRepository;

final class ChannelProductRepository extends AbstractRepository implements ChannelProductRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('channel_products')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('channel_products')),
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
        $row = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
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

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('variant_id', $variant['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findByMaster(string $businessId, string $masterExternalId): array
    {
        $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
        $master = $masterRepo->find($businessId, $masterExternalId);
        if (!$master) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('master_id', $master['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findByVariantOption(string $businessId, string $optionExternalId): array
    {
        $optionRepo = new ProductVariantOptionRepository($this->db, fn (string $name) => $this->prefix . $name);
        $option = $optionRepo->find($businessId, $optionExternalId);
        if (!$option) {
            return [];
        }

        $rows = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('variant_option_id', $option['id'])
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findByChannel(string $businessId, string $channel, array $filters = [], int $offset = 0, int $size = 50, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('channel', $channel);

        // Apply additional filters
        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['publish_status']) && is_string($filters['publish_status']) && $filters['publish_status'] !== '') {
            $query->where('publish_status', $filters['publish_status']);
        }
        if (isset($filters['channel_account_id']) && is_string($filters['channel_account_id']) && $filters['channel_account_id'] !== '') {
            $query->where('channel_account_id', $filters['channel_account_id']);
        }

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = (clone $query)->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    public function findByChannelAndExternalId(string $businessId, string $channel, string $channelProductId): ?array
    {
        $row = $this->db->select('*')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('channel', $channel)
            ->where('channel_product_id', $channelProductId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'channel_' . bin2hex(random_bytes(8));
        }
        $now = time();

        // Resolve variant_option_id, variant_id, and master_id
        $variantOptionId = $data['variant_option_id'] ?? null;
        $variantId = $data['variant_id'] ?? null;
        $masterId = $data['master_id'] ?? null;

        // If variant_option_id provided, resolve variant_id and master_id from it
        if ($variantOptionId && !$variantId && !$masterId) {
            $optionRepo = new ProductVariantOptionRepository($this->db, fn (string $name) => $this->prefix . $name);
            $option = $optionRepo->find($businessId, $data['variant_option_external_id'] ?? '');
            if ($option) {
                $variantOptionId = $option['id'];
                $variantId = $option['variant_id'];
                $masterId = $option['master_id'];
            }
        } elseif (isset($data['variant_option_external_id']) && !$variantOptionId) {
            $optionRepo = new ProductVariantOptionRepository($this->db, fn (string $name) => $this->prefix . $name);
            $option = $optionRepo->find($businessId, $data['variant_option_external_id']);
            if ($option) {
                $variantOptionId = $option['id'];
                if (!$variantId) $variantId = $option['variant_id'];
                if (!$masterId) $masterId = $option['master_id'];
            }
        }

        // Fallback to variant_external_id
        if (!$variantId && isset($data['variant_external_id'])) {
            $variantRepo = new ProductVariantRepository($this->db, fn (string $name) => $this->prefix . $name);
            $variant = $variantRepo->find($businessId, $data['variant_external_id']);
            $variantId = $variant['id'] ?? null;
            $masterId = $variant['master_id'] ?? null;
        }

        // Fallback to master_external_id
        if (!$masterId && isset($data['master_external_id'])) {
            $masterRepo = new ProductMasterRepository($this->db, fn (string $name) => $this->prefix . $name);
            $master = $masterRepo->find($businessId, $data['master_external_id']);
            $masterId = $master['id'] ?? null;
        }

        if (!$variantId || !$masterId) {
            throw new \InvalidArgumentException('variant_id (or variant_external_id/variant_option_external_id) and master_id (or master_external_id) are required');
        }

        // Build JSON fields
        $images = isset($data['images']) && is_array($data['images']) ? json_encode($data['images']) : ($data['images'] ?? null);
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode($data['tags']) : ($data['tags'] ?? null);
        $attributes = isset($data['attributes']) && is_array($data['attributes']) ? json_encode($data['attributes']) : ($data['attributes'] ?? null);

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
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
            'images' => $images,
            'tags' => $tags,
            'attributes' => $attributes,
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

        $existing = $this->db->select('id')->from($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('channel_products'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
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
        $result = $this->db->delete($this->table('channel_products'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }

    public function updateStock(string $businessId, string $externalId, int $stock): array
    {
        $values = [
            'channel_stock' => max(0, $stock),
            'last_synced_at' => time(),
            'updated_at' => time(),
        ];

        $result = $this->db->update($this->table('channel_products'), $values, [
            'business_id' => $businessId,
            'external_id' => $externalId,
        ])->run();

        if ($result > 0) {
            return $this->find($businessId, $externalId) ?? [];
        }

        throw new \InvalidArgumentException("Channel product not found: $externalId");
    }

    public function updatePublishStatus(string $businessId, string $externalId, string $status): array
    {
        $validStatuses = ['unpublished', 'published', 'pending', 'failed', 'rejected'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid publish_status: $status");
        }

        $values = [
            'publish_status' => $status,
            'updated_at' => time(),
        ];

        if ($status === 'published') {
            $values['published_at'] = time();
            $values['status'] = 'active';
        }

        $result = $this->db->update($this->table('channel_products'), $values, [
            'business_id' => $businessId,
            'external_id' => $externalId,
        ])->run();

        if ($result > 0) {
            return $this->find($businessId, $externalId) ?? [];
        }

        throw new \InvalidArgumentException("Channel product not found: $externalId");
    }

    public function syncToChannel(string $businessId, string $externalId, array $channelData): array
    {
        $channelProduct = $this->find($businessId, $externalId);
        if (!$channelProduct) {
            throw new \InvalidArgumentException("Channel product not found: $externalId");
        }

        $values = [
            'channel_product_id' => $channelData['channel_product_id'] ?? $channelProduct['channel_product_id'],
            'channel_variant_id' => $channelData['channel_variant_id'] ?? $channelProduct['channel_variant_id'],
            'channel_category_id' => $channelData['channel_category_id'] ?? $channelProduct['channel_category_id'],
            'channel_url' => $channelData['channel_url'] ?? $channelProduct['channel_url'],
            'channel_stock' => $channelData['channel_stock'] ?? $channelProduct['channel_stock'],
            'last_synced_at' => time(),
            'updated_at' => time(),
            'payload' => $this->encode(array_merge(
                $this->decode($channelProduct['payload'] ?? '{}'),
                ['last_sync' => $channelData]
            )),
        ];

        $this->db->update($this->table('channel_products'), $values, ['id' => $channelProduct['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['variant_option_external_id']) && is_string($filters['variant_option_external_id']) && $filters['variant_option_external_id'] !== '') {
            $optionRepo = new ProductVariantOptionRepository($this->db, fn (string $name) => $this->prefix . $name);
            $option = $optionRepo->find($businessId, $filters['variant_option_external_id']);
            if ($option) {
                $query->where('variant_option_id', $option['id']);
            }
        }

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

        if (isset($filters['channel']) && is_string($filters['channel']) && $filters['channel'] !== '') {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['channel_account_id']) && is_string($filters['channel_account_id']) && $filters['channel_account_id'] !== '') {
            $query->where('channel_account_id', $filters['channel_account_id']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('sku', 'LIKE', '%' . $keyword . '%')
                ->orWhere('external_id', 'LIKE', '%' . $keyword . '%')
                ->orWhere('title', 'LIKE', '%' . $keyword . '%')
                ->orWhere('channel_product_id', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['publish_status']) && is_string($filters['publish_status']) && $filters['publish_status'] !== '') {
            $query->where('publish_status', $filters['publish_status']);
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'sku', 'sale_price', 'channel', 'published_at'], true)) {
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