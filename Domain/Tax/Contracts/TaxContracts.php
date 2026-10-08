<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Tax\Contracts;

interface TaxRateRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;

    public function find(string $businessId, string $externalId): ?array;

    public function findByCode(string $businessId, string $code): ?array;

    public function getActiveRates(string $businessId, int $timestamp = 0): array;

    public function getRatesForCategory(string $businessId, string $category, int $timestamp = 0): array;

    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $externalId): bool;
}

interface TaxCalculatorInterface
{
    public function calculateForItem(
        string $businessId,
        Money $unitPrice,
        int $quantity,
        string $productCategory,
        int $timestamp = 0
    ): array;

    public function calculateForOrder(
        string $businessId,
        array $items,
        int $timestamp = 0
    ): array;
}