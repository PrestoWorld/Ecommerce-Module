<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Contracts;

interface AffiliateRepositoryInterface
{
    public function searchProducts(array $filters, int $offset, int $size, array $sort = []): array;

    public function findProduct(string $channel, string $externalId): ?array;

    public function saveProduct(array $data): void;

    public function publishers(array $filters, int $offset, int $size): array;

    public function savePublisher(array $data): void;

    public function orders(array $filters, int $size, array $cursor = []): array;

    public function saveOrder(array $data): void;

    public function saveShareLink(array $data): void;

    public function findShareLink(string $code): ?array;
}
