<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Headless\Handler;

use PrestoWorld\Modules\Ecommerce\Contracts\CustomerRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class CustomerResourceHandler extends ResourceHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        string $businessId,
    ) {
        parent::__construct($businessId);
    }

    public function list(Query $query): array
    {
        $result = $this->customers->search(
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
        $row = $this->customers->find($this->businessId, $key);

        return $row === null ? null : $this->unwrap($row);
    }

    public function create(array $data): array
    {
        return $this->unwrap($this->customers->save($this->businessId, $data));
    }

    public function update(string $key, array $data): ?array
    {
        if ($this->customers->find($this->businessId, $key) === null) {
            return null;
        }

        $data['external_id'] = $key;

        return $this->unwrap($this->customers->save($this->businessId, $data));
    }
}