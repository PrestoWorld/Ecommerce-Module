<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Headless\Handler;

use PrestoWorld\Modules\HeadlessCMS\Exceptions\HeadlessException;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\HeadlessCMS\Resource\AbstractResourceHandler;

/**
 * Shared behaviour for Ecommerce resource handlers: resolves the active
 * business id, normalizes incoming query filters and unwraps stored payloads.
 */
abstract class ResourceHandler extends AbstractResourceHandler
{
    public function __construct(
        protected string $businessId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Query $query): array
    {
        $filters = $query->filters;

        if ($query->search !== null && !isset($filters['keyword'])) {
            $filters['keyword'] = $query->search;
        }

        unset($filters['business_id'], $filters['businessId']);

        return $filters;
    }

    protected function businessIdFor(Query $query): string
    {
        $override = $query->filters['business_id'] ?? $query->filters['businessId'] ?? null;

        return is_string($override) && $override !== '' ? $override : $this->businessId;
    }

    /**
     * @param array<mixed, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected function unwrap(array $row): array
    {
        $payload = $row['payload'] ?? null;

        if (is_array($payload) && $payload !== []) {
            return $this->stringKeyed($payload);
        }

        unset($row['payload'], $row['business_id']);

        return $this->stringKeyed($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function items(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = $this->unwrap($row);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function assertExternalId(array $data): void
    {
        $id = $data['external_id'] ?? $data['id'] ?? null;

        if (!is_scalar($id) || (string) $id === '') {
            throw HeadlessException::badRequest('external_id is required.');
        }
    }

    protected function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<mixed, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}