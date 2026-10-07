<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface SyncCursorRepositoryInterface
{
    public function get(string $resource): ?array;

    public function set(string $resource, array $cursor): void;
}
