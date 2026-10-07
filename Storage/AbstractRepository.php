<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage;

use Cycle\Database\DatabaseInterface;

abstract class AbstractRepository
{
    public function __construct(
        protected DatabaseInterface $db,
        protected string $prefix,
    ) {
    }

    protected function table(string $name): string
    {
        return $this->prefix . $name;
    }

    protected function encode(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    protected function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (mixed $row) => $this->decoded($row), $rows);
    }

    protected function decoded(mixed $row): array
    {
        if (!is_array($row)) {
            return [];
        }

        if (isset($row['payload'])) {
            $payload = $row['payload'];
            $row['payload'] = $this->decode(is_string($payload) ? $payload : null);
        }

        return $row;
    }

    protected function int(mixed $row, string $key, int $default = 0): int
    {
        if (!is_array($row) || !array_key_exists($key, $row)) {
            return $default;
        }

        $value = $row[$key];

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    protected function ts(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);

            return $timestamp === false ? null : $timestamp;
        }

        return null;
    }

    protected function camel(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $result[$this->toCamel((string) $key)] = $value;
        }

        return $result;
    }

    protected function toCamel(string $key): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }
}