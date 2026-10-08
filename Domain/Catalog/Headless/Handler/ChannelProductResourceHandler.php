<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class ChannelProductResourceHandler extends ResourceHandler
{
    public function __construct(
        private ChannelProductRepositoryInterface $channelProducts,
        string $businessId,
    ) {
        parent::__construct($businessId);
    }

    public function list(Query $query): array
    {
        $result = $this->channelProducts->search(
            $this->businessIdFor($query),
            $this->filters($query),
            $query->offset,
            $query->limit,
            $query->sort,
        );

        return [
            'items' => $this->items($result['items'] ?? []),
            'total' => $this->toInt($result['total'] ?? 0),
        ];
    }

    public function read(string $key): ?array
    {
        $row = $this->channelProducts->find($this->businessId, $key);

        return $row === null ? null : $this->unwrap($row);
    }

    public function create(array $data): array
    {
        $this->assertExternalId($data);

        return $this->unwrap($this->channelProducts->save($this->businessId, $data));
    }

    public function update(string $key, array $data): ?array
    {
        if ($this->channelProducts->find($this->businessId, $key) === null) {
            return null;
        }

        $data['external_id'] = $key;

        return $this->unwrap($this->channelProducts->save($this->businessId, $data));
    }

    public function delete(string $key): bool
    {
        return $this->channelProducts->delete($this->businessId, $key);
    }

    // Channel-specific actions
    public function updateStock(string $key, int $stock): ?array
    {
        $product = $this->channelProducts->find($this->businessId, $key);
        if (!$product) {
            return null;
        }

        return $this->unwrap($this->channelProducts->updateStock($this->businessId, $key, $stock));
    }

    public function publish(string $key): ?array
    {
        $product = $this->channelProducts->find($this->businessId, $key);
        if (!$product) {
            return null;
        }

        return $this->unwrap($this->channelProducts->updatePublishStatus($this->businessId, $key, 'published'));
    }

    public function unpublish(string $key): ?array
    {
        $product = $this->channelProducts->find($this->businessId, $key);
        if (!$product) {
            return null;
        }

        return $this->unwrap($this->channelProducts->updatePublishStatus($this->businessId, $key, 'unpublished'));
    }

    public function sync(string $key, array $channelData): ?array
    {
        $product = $this->channelProducts->find($this->businessId, $key);
        if (!$product) {
            return null;
        }

        return $this->unwrap($this->channelProducts->syncToChannel($this->businessId, $key, $channelData));
    }

    // Custom filters for channel queries
    public function listByChannel(string $channel, Query $query): array
    {
        $filters = $this->filters($query);
        $filters['channel'] = $channel;

        $result = $this->channelProducts->findByChannel(
            $this->businessIdFor($query),
            $channel,
            $filters,
            $query->offset,
            $query->limit,
            $query->sort,
        );

        return [
            'items' => $this->items($result['items'] ?? []),
            'total' => $this->toInt($result['total'] ?? 0),
        ];
    }

    public function findByChannelAndExternalId(string $channel, string $channelProductId): ?array
    {
        $row = $this->channelProducts->findByChannelAndExternalId($this->businessId, $channel, $channelProductId);

        return $row === null ? null : $this->unwrap($row);
    }

    // Custom actions for hierarchy
    public function findByVariant(string $variantKey): array
    {
        return $this->channelProducts->findByVariant($this->businessId, $variantKey);
    }

    public function findByMaster(string $masterKey): array
    {
        return $this->channelProducts->findByMaster($this->businessId, $masterKey);
    }
}