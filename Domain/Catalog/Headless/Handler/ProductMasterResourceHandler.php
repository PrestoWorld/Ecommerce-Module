<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class ProductMasterResourceHandler extends ResourceHandler
{
    public function __construct(
        private ProductMasterRepositoryInterface $masters,
        string $businessId,
    ) {
        parent::__construct($businessId);
    }

    public function list(Query $query): array
    {
        $result = $this->masters->search(
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
        $row = $this->masters->find($this->businessId, $key);

        if ($row === null) {
            return null;
        }

        // Include variants and channel products
        $row['variants'] = $this->masters->getVariants($this->businessId, $key);
        $row['channel_products'] = $this->masters->getChannelProducts($this->businessId, $key);

        return $this->unwrap($row);
    }

    public function create(array $data): array
    {
        $this->assertExternalId($data);

        return $this->unwrap($this->masters->save($this->businessId, $data));
    }

    public function update(string $key, array $data): ?array
    {
        if ($this->masters->find($this->businessId, $key) === null) {
            return null;
        }

        $data['external_id'] = $key;

        return $this->unwrap($this->masters->save($this->businessId, $data));
    }

    public function delete(string $key): bool
    {
        return $this->masters->delete($this->businessId, $key);
    }

    // Custom actions for hierarchy
    public function getVariants(string $masterKey): array
    {
        $master = $this->masters->find($this->businessId, $masterKey);
        if (!$master) {
            return [];
        }

        return $this->masters->getVariants($this->businessId, $masterKey);
    }

    public function getChannelProducts(string $masterKey): array
    {
        $master = $this->masters->find($this->businessId, $masterKey);
        if (!$master) {
            return [];
        }

        return $this->masters->getChannelProducts($this->businessId, $masterKey);
    }
}