# Ecommerce — Thuộc tính sản phẩm (Domain/Attribute)

Tính năng **thuộc tính sản phẩm (EAV)** của module Ecommerce: định nghĩa thuộc tính
(chung cho mọi sản phẩm) + gán giá trị theo từng sản phẩm/variant/option, với thuộc
tính đặc biệt **`warehouse_link`** liên kết sản phẩm ↔ kho — nền tảng cho tính giá
theo kho của module
[WarehousePricing](../../../WarehousePricing/README.md).

```
product_attributes (định nghĩa)          product_attribute_values (gán theo sản phẩm)
├── code: condition                      ├── attribute_code ──► definitions
├── type: condition|warehouse_link|...   ├── product_external_id (bắt buộc)
├── options (JSON, type=select)          ├── variant_external_id ('' = mọi variant)
├── is_combinable ──► tổ hợp tính giá    ├── option_external_id ('' = mọi option)
└── status: active|archived              ├── warehouse_code/store_code (warehouse_link)
                                         └── value / value_percent (% mới)
```

## Cấu trúc

```
modules/Ecommerce/Domain/Attribute/
├── manifest.json                    # domain manifest (migrations metadata)
├── Module.php                       # bind 2 repository singleton (prefix ecommerce.table_prefix)
├── Contracts/
│   ├── ProductAttributeRepositoryInterface.php        # search/all/find/save/delete
│   └── ProductAttributeValueRepositoryInterface.php   # search/findByProduct/save/delete
├── Migrations/001_attribute_tables.php                # 2 bảng (UUID PK)
├── Storage/
│   ├── AbstractRepository.php
│   ├── ProductAttributeRepository.php                 # upsert theo (business, code)
│   └── ProductAttributeValueRepository.php            # upsert theo key 5 cột
└── README.md (file này)
```

Controller admin: `modules/Ecommerce/Http/AdminProductAttributesController.php`.
Domain được đăng ký trong `Ecommerce\Module::registerDomainModules()` (attribute có
`type` theo định nghĩa trong migration — xem dưới).

## Bảng database

Prefix: `ecommerce.table_prefix` (`.env` → `bow_`). PK **UUID string (VARCHAR 36)**.
Migration auto-discovered: `Application::moduleMigrationPaths()` quét cả
`modules/<Module>/Domain/<Slice>/Migrations/`.

### `product_attributes` — định nghĩa thuộc tính

| Cột | Ý nghĩa |
|---|---|
| `code` | unique `(business_id, code)`, lowercase — vd `condition`, `warehouse`, `binding_type`, `color` |
| `name`, `description` | hiển thị |
| `type` | `warehouse_link` \| `condition` \| `select` \| `number` \| `boolean` \| `text` |
| `options` | JSON danh sách giá trị hợp lệ (`type=select`) |
| `value_source` | `warehouse` \| `store` — với `type=warehouse_link` |
| `applies_to` | `product` \| `variant` \| `option` — cấp gốc gán giá trị |
| `is_combinable` | `true` = tham gia tổ hợp `attribute_key` tính giá |
| `is_required` | bắt buộc khi gán |
| `sort_order`, `status` | thứ tự (`all()` sort `sort_order, code`) và trạng thái |

`type` đặc biệt:

- **`warehouse_link`** — giá trị là mã kho (`warehouse_code`), liên kết sản phẩm ↔ kho.
  Một sản phẩm có thể gán nhiều kho (nhiều row).
- **`condition`** — tình trạng sản phẩm (mới/cũ); ghép với `value_percent` (% mới)
  quyết định % giảm giá trong WarehousePricing.

### `product_attribute_values` — gán giá trị cho sản phẩm

Upsert key: `(business_id, attribute_code, product_external_id, variant_external_id,
option_external_id, warehouse_code)` — các cột scope `''` nghĩa là *mọi mức*.

- `value` — giá trị (`text`, JSON nếu object); `value_percent` — % (vd % mới).
- `warehouse_code`/`store_code` — với `warehouse_link`. **Derivation rule**: nếu
  `warehouse_code` trống và data truyền `attribute_type=warehouse_link`, lấy mã kho
  từ chính `value`.
- Tham chiếu sản phẩm bằng `product_external_id` (chuỗi) — không FK, dùng được với
  cả `bow_products`, `bow_product_masters`, `bow_product_variant_options`.

## Container bindings

```php
PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeRepositoryInterface
    => Storage\ProductAttributeRepository
PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeValueRepositoryInterface
    => Storage\ProductAttributeValueRepository
```

Singleton (lazy) — inject 2 interface này vào service/controller bất kỳ
(pattern chung của 10 domain trong Ecommerce).

## Admin API

Đăng ký trong `Ecommerce\Module::registerAdminRoutes()`; tự bảo vệ bởi
`AdminAuthMiddleware` (prefix `/api/admin`). `business_id` lấy từ
`ecommerce.headless.business_id` fallback `catalog.headless.business_id`.

### Định nghĩa (`{code}` = business key)

```
GET    /api/admin/product-attributes                # list: ?code=&type=&status=&page=&per_page=
POST   /api/admin/product-attributes                # tạo/upsert theo code
PUT    /api/admin/product-attributes/{code}         # update (code lấy từ route)
PATCH  /api/admin/product-attributes/{code}
DELETE /api/admin/product-attributes/{code}
```

```bash
# Khai báo thuộc tính liên kết kho + tình trạng:
curl -X POST /api/admin/product-attributes -d '{
  "code": "warehouse", "name": "Kho", "type": "warehouse_link", "value_source": "warehouse"
}'
curl -X POST /api/admin/product-attributes -d '{
  "code": "condition", "name": "Tình trạng", "type": "condition"
}'
```

Lỗi validation (`code` trống, `type` sai) → `400 {"error": "..."}`; xóa không thấy → `404`.

### Giá trị (id = UUID)

```
GET    /api/admin/product-attributes/values          # ?product_external_id=&attribute_code=&warehouse_code=
POST   /api/admin/product-attributes/values          # tạo/upsert value
DELETE /api/admin/product-attributes/values/{id}
```

```bash
# Liên kết sản phẩm PROD-1 với kho WH-1:
curl -X POST /api/admin/product-attributes/values -d '{
  "attribute_code": "warehouse",
  "attribute_type": "warehouse_link",
  "product_external_id": "PROD-1",
  "value": "WH-1"
}'
# → warehouse_code tự suy = "WH-1"
```

## Tương thích WarehousePricing

WarehousePricing đọc/ghi **trực tiếp 2 bảng này** qua bridge
(`WarehousePricing\Storage\AttributeRepositoryBridge` +
`AttributeValueRepositoryBridge`) khi binding Ecommerce có sẵn:

- Định nghĩa `type=condition` / `type=warehouse_link` trong bảng trên chính là input
  để `PricingResolver` tách tình trạng / kho khỏi tổ hợp thuộc tính.
- Row `warehouse_link` trong `product_attribute_values` = **stored warehouse link**
  (resolve không truyền `?warehouse=` vẫn trỏ đúng kho).
- Các thuộc tính khác (kể cả không khai báo) combine thành `attribute_key` xuất giá
  tuyệt đối cho nhanh.vn / Shopee / Lazada.

Chi tiết thuật toán: [WarehousePricing README](../../../WarehousePricing/README.md).

## Test

```bash
./vendor/bin/phpunit modules/Ecommerce/tests    # 51 tests (43 cũ + 8 attribute)
./vendor/bin/phpunit tests/Unit/Modules/WarehousePricing   # 14 tests (gồm 5 tương thích)
```

`ProductAttributeRepositoryTest` phủ: UUID PK, upsert theo code (giữ id), filter/sort,
validation `type`, derivation `warehouse_code`, upsert key 5 cột, scope variant/option.

## Ghi chú

- **Schema là superset** của `pricing_attributes` / `pricing_attribute_values`
  (WarehousePricing) — cùng signature repo, cùng derivation rule, nên bridge là
  delegate thuần không cần map field.
- **Không trùng bảng với BookCatalog**: `product_variant_options` (cột vật lý
  `condition, binding_type, format, color, size` + JSON `attributes`) là tầng SKU
  catalog; 2 bảng EAV này là tầng *định nghĩa chung + gán theo sản phẩm* cho
  pricing. Không FK qua lại — mapping bằng `product_external_id` + tên `code`.
- PK UUID: `setPrimaryKeys(['id'])` + `string(36)` (không dùng `primary()` — Cycle
  ép INTEGER); `down()` dùng `DROP IF EXISTS` trần tránh SQLite lock.
