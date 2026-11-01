<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts;

/**
 * Gán giá trị thuộc tính cho sản phẩm (bảng product_attribute_values).
 *
 * Scope: product (bắt buộc) → variant ('' = mọi variant) → option ('' = mọi option).
 * Với attribute type=warehouse_link, warehouse_code/store_code liên kết
 * sản phẩm ↔ kho/cửa hàng — nguồn dữ liệu cho WarehousePricing resolver.
 */
interface ProductAttributeValueRepositoryInterface
{
    /**
     * @param array{attribute_code?: string, product_external_id?: string, warehouse_code?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(string $businessId, array $filters = [], int $offset = 0, int $limit = 50): array;

    /** Mọi giá trị thuộc tính của 1 sản phẩm. @return list<array<string, mixed>> */
    public function findByProduct(string $businessId, string $productExternalId): array;

    /**
     * Upsert theo (business_id, attribute_code, product, variant, option, warehouse).
     * Bắt buộc: attribute_code, product_external_id.
     */
    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $id): bool;
}
