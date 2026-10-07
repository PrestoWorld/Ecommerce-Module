<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface ShippingDirectoryRepositoryInterface
{
    public function locations(string $version, string $type, ?int $parentId = null): array;

    public function saveLocations(array $rows): void;

    public function carriers(): array;

    public function saveCarriers(array $rows): void;
}
