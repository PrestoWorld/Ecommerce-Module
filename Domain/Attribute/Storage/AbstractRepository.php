<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Core\Uuid;

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

    /** PK là UUID string (xem Migrations/001_attribute_tables). */
    protected function uuid(): string
    {
        return Uuid::v4();
    }

    protected function strId(mixed $row): string
    {
        return is_array($row) && isset($row['id']) ? (string) $row['id'] : '';
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

    /** @return list<array<string, mixed>> */
    protected function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (mixed $row) => $this->decoded($row), $rows);
    }

    /** @return array<string, mixed> */
    protected function decoded(mixed $row): array
    {
        if (!is_array($row)) {
            return [];
        }

        return $row;
    }

    /**
     * Chuẩn hóa giá trị cột scope: '' nghĩa là "mọi mức" (không dùng NULL
     * để so khớp equality đơn giản trên mọi driver).
     */
    protected function scopeValue(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }
}
