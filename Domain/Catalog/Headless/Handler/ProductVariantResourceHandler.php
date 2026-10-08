<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class ProductVariantResourceHandler extends ResourceHandler
{
    public function __construct(
        private ProductVariantRepositoryInterface $variants,
        string $businessId,
    ) {
        parent::__construct($businessId);
    }

    public function list(Query $query): array
    {
        $result = $this->variants->search(
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
        $row = $this->variants->find($this->businessId, $key);

        if ($row === null) {
            return null;
        }

        // Include channel products
        $row['channel_products'] = $this->variants->getChannelProducts($this->businessId, $key);

        return $this->unwrap($row);
    }

    public function create(array $data): array
    {
        $this->assertExternalId($data);

        return $this->unwrap($this->variants->save($this->businessId, $data));
    }

    public function update(string $key, array $data): ?array
    {
        if ($this->variants->find($this->businessId, $key) === null) {
            return null;
        }

        $data['external_id'] = $key;

        return $this->unwrap($this->variants->save($this->businessId, $data));
    }

    public function delete(string $key): bool
    {
        return $this->variants->delete($this->businessId, $key);
    }

    // Stock management actions
    public function adjustStock(string $key, int $quantity, string $operation = 'add'): ?array
    {
        $variant = $this->variants->find($this->businessId, $key);
        if (!$variant) {
            return null;
        }

        return $this->unwrap($this->variants->updateStock($this->businessId, $key, $quantity, $operation));
    }

    public function reserveStock(string $key, int $quantity): ?array
    {
        $variant = $this->variants->find($this->businessId, $key);
        if (!$variant) {
            return null;
        }

        return $this->unwrap($this->variants->reserveStock($this->businessId, $key, $quantity));
    }

    public function releaseStock(string $key, int $quantity): ?array
    {
        $variant = $this->variants->find($this->businessId, $key);
        if (!$variant) {
            return null;
        }

        return $this->unwrap($this->variants->releaseStock($this->businessId, $key, $quantity));
    }

    // Custom actions for hierarchy
    public function getChannelProducts(string $variantKey): array
    {
        $variant = $this->variants->find($this->businessId, $variantKey);
        if (!$variant) {
            return [];
        }

        return $this->variants->getChannelProducts($this->businessId, $variantKey);
    }

    public function findByMaster(string $masterKey): array
    {
        return $this->variants->findByMaster($this->businessId, $masterKey);
    }
}