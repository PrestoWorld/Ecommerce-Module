<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 001_attribute_tables (Ecommerce Domain/Attribute)
 *
 * Thuộc tính sản phẩm cấp module Ecommerce:
 *
 *  - product_attributes        định nghĩa thuộc tính (EAV definitions).
 *                              type=warehouse_link: thuộc tính đặc biệt —
 *                              giá trị trỏ sản phẩm tới kho.
 *                              type=condition: tình trạng (mới/cũ, % mới);
 *                              type=select/number/boolean/text: thuộc tính thường.
 *  - product_attribute_values  gán giá trị cho sản phẩm/variant/option.
 *                              warehouse_code/store_code liên kết sản phẩm ↔
 *                              kho/cửa hàng (dùng bởi WarehousePricing).
 *
 * Cột là SUPERSET của pricing_attributes / pricing_attribute_values
 * (modules/WarehousePricing) — WarehousePricing đọc/ghi qua bridge
 * (Storage\AttributeRepositoryBridge) khi Ecommerce binding có sẵn.
 *
 * PK UUID string (VARCHAR 36) — chuẩn mới, không lộ số tự tăng.
 * Không FK sang products/product_masters: attribute values tham chiếu qua
 * external_id chuỗi để không phụ thuộc schema bảng khác domain.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->attributes($db, $prefix);
        $this->attributeValues($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        // DROP IF EXISTS trần — không gọi hasTable()/getSchema() trước vì các
        // call đó giữ read lock trên SQLite và làm DROP TABLE fail
        // (bài học từ migration 002_social_feed_uuid).
        $db->execute('DROP TABLE IF EXISTS ' . $prefix . 'product_attribute_values');
        $db->execute('DROP TABLE IF EXISTS ' . $prefix . 'product_attributes');
    }

    private function attributes(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'product_attributes';
        if ($db->hasTable($table)) {
            return;
        }

        $schema = $this->schema($db, $table);
        // PK chuỗi phải NOT NULL — Cycle default nullable=true, MySQL bỏ qua
        // PRIMARY KEY trên cột NULL (lỗi 1171).
        $schema->column('id')->string(36)->nullable(false);
        $schema->column('business_id')->string(64);
        $schema->column('code')->string(64); // vd: warehouse, condition, binding_type, color
        $schema->column('name')->string(255);
        $schema->column('type')->string(32)->defaultValue('text'); // warehouse_link | condition | select | number | boolean | text
        $schema->column('options')->text()->nullable(); // JSON danh sách giá trị cho type=select
        $schema->column('value_source')->string(32)->nullable(); // warehouse | store (type=warehouse_link)
        $schema->column('applies_to')->string(32)->defaultValue('product'); // product | variant | option
        $schema->column('is_combinable')->boolean()->defaultValue(true); // tham gia tổ hợp tính giá
        $schema->column('is_required')->boolean()->defaultValue(false);
        $schema->column('description')->string(500)->nullable();
        $schema->column('sort_order')->integer()->defaultValue(0);
        $schema->column('status')->string(16)->defaultValue('active');
        $schema->column('created_at')->bigInteger()->defaultValue(0);
        $schema->column('updated_at')->bigInteger()->defaultValue(0);
        $schema->setPrimaryKeys(['id']);

        $schema->index(['business_id', 'code'])->unique();
        $schema->index(['business_id', 'type']);
        $schema->index(['business_id', 'status']);
        $schema->save();
    }

    private function attributeValues(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'product_attribute_values';
        if ($db->hasTable($table)) {
            return;
        }

        $schema = $this->schema($db, $table);
        $schema->column('id')->string(36)->nullable(false); // PK — xem lỗi 1171 ở bảng trên
        $schema->column('business_id')->string(64);
        $schema->column('attribute_code')->string(64);
        $schema->column('product_external_id')->string(64);
        $schema->column('variant_external_id')->string(64)->defaultValue(''); // '' = mọi variant
        $schema->column('option_external_id')->string(64)->defaultValue(''); // '' = mọi option
        // warehouse_code/store_code: '' = không gắn; có giá trị khi attribute
        // type=warehouse_link (liên kết sản phẩm ↔ kho/cửa hàng).
        $schema->column('warehouse_code')->string(64)->defaultValue('');
        $schema->column('store_code')->string(64)->defaultValue('');
        $schema->column('value')->text()->nullable(); // giá trị hiển thị/so sánh (JSON nếu object)
        $schema->column('value_percent')->integer()->nullable(); // % (vd % mới của tình trạng)
        $schema->column('created_at')->bigInteger()->defaultValue(0);
        $schema->column('updated_at')->bigInteger()->defaultValue(0);
        $schema->setPrimaryKeys(['id']);

        $schema->index(['business_id', 'product_external_id']);
        $schema->index(['business_id', 'attribute_code', 'product_external_id']);
        $schema->index(['business_id', 'warehouse_code']);
        $schema->index(['business_id', 'store_code']);
        $schema->index(['business_id', 'option_external_id']);
        $schema->save();
    }

    private function schema(DatabaseInterface $db, string $name): AbstractTable
    {
        $table = $db->table($name);

        if (!$table instanceof Table) {
            throw new \RuntimeException("Expected concrete Cycle table for {$name}, got " . $table::class);
        }

        return $table->getSchema();
    }
};
