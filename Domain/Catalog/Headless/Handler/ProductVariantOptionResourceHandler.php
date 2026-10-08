<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class ProductVariantOptionResourceHandler extends ResourceHandler
{
    public function __construct(
        private ProductVariantOptionRepositoryInterface $options,
        string $businessId,
    ) {
        parent::__construct($businessId);
    }

    public function list(Query $query): array
    {
        $result = $this->options->search(
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
        $row = $this->options->find($this->businessId, $key);

        if ($row === null) {
            return null;
        }

        // Include channel products
        $row['channel_products'] = $this->options->getChannelProducts($this->businessId, $key);

        return $this->unwrap($row);
    }

    public function create(array $data): array
    {
        $this->assertExternalId($data);

        return $this->unwrap($this->options->save($this->businessId, $data));
    }

    public function update(string $key, array $data): ?array
    {
        if ($this->options->find($this->businessId, $key) === null) {
            return null;
        }

        $data['external_id'] = $key;

        return $this->unwrap($this->options->save($this->businessId, $data));
    }

    public function delete(string $key): bool
    {
        return $this->options->delete($this->businessId, $key);
    }

    // Stock management actions
    public function adjustStock(string $key, int $quantity, string $operation = 'add'): ?array
    {
        $option = $this->options->find($this->businessId, $key);
        if (!$option) {
            return null;
        }

        return $this->unwrap($this->options->updateStock($this->businessId, $key, $quantity, $operation));
    }

    public function reserveStock(string $key, int $quantity): ?array
    {
        $option = $this->options->find($this->businessId, $key);
        if (!$option) {
            return null;
        }

        return $this->unwrap($this->options->reserveStock($this->businessId, $key, $quantity));
    }

    public function releaseStock(string $key, int $quantity): ?array
    {
        $option = $this->options->find($this->businessId, $key);
        if (!$option) {
            return null;
        }

        return $this->unwrap($this->options->releaseStock($this->businessId, $key, $quantity));
    }

    // Custom actions for hierarchy
    public function getChannelProducts(string $optionKey): array
    {
        $option = $this->options->find($this->businessId, $optionKey);
        if (!$option) {
            return [];
        }

        return $this->options->getChannelProducts($this->businessId, $optionKey);
    }

    public function findByVariant(string $variantKey): array
    {
        return $this->options->findByVariant($this->businessId, $variantKey);
    }

    public function findByAttributes(string $variantKey, array $attributes): ?array
    {
        return $this->options->findByAttributes($this->businessId, $variantKey, $attributes);
    }
}