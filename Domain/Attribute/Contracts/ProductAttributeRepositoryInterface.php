<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts;

/**
 * Định nghĩa thuộc tính sản phẩm (EAV definitions — bảng product_attributes).
 *
 * Thuộc tính tổ hợp (is_combinable) được combine với thuộc tính khác thành
 * attribute_key (xem PrestoWorld\Modules\WarehousePricing\Support\AttributeKey)
 * để truy xuất giá/tồn theo tổ hợp. type đặc biệt:
 *  - warehouse_link: giá trị trỏ sản phẩm tới kho (value_source = warehouse|store)
 *  - condition:      tình trạng (mới/cũ, % mới)
 *  - select/number/boolean/text: thuộc tính tổ hợp thường
 */
interface ProductAttributeRepositoryInterface
{
    /**
     * @param array{code?: string, type?: string, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(string $businessId, array $filters = [], int $offset = 0, int $limit = 50): array;

    /** Mọi attribute active (sắp sort_order, code) — dùng khi resolve giá. */
    public function all(string $businessId): array;

    public function find(string $businessId, string $code): ?array;

    /** Upsert theo (business_id, code). Bắt buộc: code; type mặc định text. */
    public function save(string $businessId, array $data): array;

    public function delete(string $businessId, string $code): bool;
}
