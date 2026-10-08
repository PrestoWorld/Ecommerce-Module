<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->productMasters($db, $prefix);
        $this->productVariants($db, $prefix);
        $this->productVariantOptions($db, $prefix);
        $this->channelProducts($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = [
            'channel_products',
            'product_variant_options',
            'product_variants',
            'product_masters',
        ];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function productMasters(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'product_masters');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('sku')->string(64)->defaultValue('');
        
        // Core book information (immutable/common)
        $table->column('title')->string(500)->defaultValue('');
        $table->column('subtitle')->string(500)->nullable();
        $table->column('authors')->text()->nullable(); // JSON array of authors
        $table->column('translators')->text()->nullable(); // JSON array
        $table->column('publisher')->string(255)->nullable();
        $table->column('original_publisher')->string(255)->nullable();
        $table->column('publication_year')->integer()->nullable();
        $table->column('isbn_13')->string(20)->nullable();
        $table->column('isbn_10')->string(20)->nullable();
        $table->column('issn')->string(20)->nullable();
        $table->column('qd_xb')->string(100)->nullable(); // Quyết định xuất bản
        $table->column('xn_dk_xb')->string(100)->nullable(); // Xin đăng ký xuất bản
        $table->column('language')->string(50)->defaultValue('vi');
        $table->column('page_count')->integer()->nullable();
        $table->column('dimensions')->string(100)->nullable(); // e.g., "14.5 x 20.5 cm"
        $table->column('weight')->integer()->nullable(); // in grams
        $table->column('binding_type')->string(50)->nullable(); // bìa mềm, bìa cứng, etc.
        $table->column('cover_image')->string(500)->nullable();
        $table->column('cover_images')->text()->nullable(); // JSON array of additional cover images
        $table->column('table_of_contents')->longText()->nullable();
        $table->column('description')->longText()->nullable();
        $table->column('description_short')->text()->nullable();
        $table->column('tags')->text()->nullable(); // JSON array
        $table->column('categories')->text()->nullable(); // JSON array
        $table->column('subject_codes')->text()->nullable(); // JSON array (BISAC, etc.)
        
        // SEO & Marketing
        $table->column('slug')->string(600)->nullable();
        $table->column('meta_title')->string(255)->nullable();
        $table->column('meta_description')->text()->nullable();
        $table->column('meta_keywords')->text()->nullable();
        
        // Status & metadata
        $table->column('status')->string(32)->defaultValue('draft'); // draft, active, archived
        $table->column('payload')->text()->nullable(); // flexible JSON for extensions
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('published_at')->bigInteger()->nullable();
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'sku']);
        $table->index(['business_id', 'status']);
        $table->index(['business_id', 'publisher']);
        $table->index(['business_id', 'publication_year']);
        $table->index(['isbn_13']);
        $table->save();
    }

    private function productVariants(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'product_variants');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('master_id')->bigInteger(); // FK to product_masters
        $table->column('sku')->string(64)->defaultValue('');
        $table->column('barcode')->string(64)->nullable();
        
        // Variant-specific info (differs per edition/print run)
        $table->column('edition')->integer()->defaultValue(1); // lần tái bản: 1, 2, 3...
        $table->column('edition_name')->string(100)->nullable(); // e.g., "Tái bản 2024", "Đặc biệt"
        $table->column('publication_year')->integer()->nullable(); // năm xuất bản của lần in này
        $table->column('publisher')->string(255)->nullable(); // NXB của lần in này
        $table->column('print_run')->integer()->nullable(); // số lượng in
        $table->column('print_date')->bigInteger()->nullable(); // ngày in
        
        // Pricing - Warehouse/Supply level
        $table->column('cover_price')->bigInteger()->defaultValue(0); // giá bìa
        $table->column('wholesale_price')->bigInteger()->defaultValue(0); // giá bán buôn / nhập kho
        $table->column('cost_price')->bigInteger()->defaultValue(0); // giá vốn thực tế
        $table->column('currency')->string(8)->defaultValue('VND');
        
        // Inventory
        $table->column('stock_on_hand')->bigInteger()->defaultValue(0);
        $table->column('stock_reserved')->bigInteger()->defaultValue(0);
        $table->column('stock_available')->bigInteger()->defaultValue(0); // computed: on_hand - reserved
        $table->column('reorder_point')->integer()->defaultValue(0);
        $table->column('reorder_qty')->integer()->defaultValue(0);
        $table->column('location')->string(100)->nullable(); // warehouse location/bin
        $table->column('supplier_id')->string(64)->nullable();
        $table->column('supplier_sku')->string(64)->nullable();
        
        // Physical attributes (may differ from master)
        $table->column('weight')->integer()->nullable(); // override master weight
        $table->column('dimensions')->string(100)->nullable(); // override master dimensions
        $table->column('cover_image')->string(500)->nullable(); // variant-specific cover
        
        // Status
        $table->column('status')->string(32)->defaultValue('active'); // active, discontinued, out_of_print
        $table->column('is_default')->boolean()->defaultValue(false); // default variant for master
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'master_id']);
        $table->index(['business_id', 'sku']);
        $table->index(['business_id', 'barcode']);
        $table->index(['business_id', 'status']);
        $table->index(['master_id', 'is_default']);
        $table->save();
    }

    private function productVariantOptions(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'product_variant_options');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('variant_id')->bigInteger(); // FK to product_variants
        $table->column('master_id')->bigInteger(); // FK to product_masters (denormalized)
        $table->column('sku')->string(64)->defaultValue('');
        $table->column('barcode')->string(64)->nullable();
        
        // Option attributes (defines the physical variation)
        $table->column('binding_type')->string(50)->nullable(); // bìa cứng, bìa mềm, bìa carton, spiral
        $table->column('condition')->string(32)->defaultValue('new'); // new, used_like_new, used_good, used_fair, damaged
        $table->column('format')->string(32)->defaultValue('physical'); // physical, ebook, audiobook, pdf
        $table->column('color')->string(50)->nullable(); // màu bìa/sách nếu có
        $table->column('size')->string(50)->nullable(); // kích thước đặc biệt
        $table->column('attributes')->text()->nullable(); // JSON: additional flexible attributes
        
        // Pricing - Warehouse/Supply level (overrides variant)
        $table->column('cover_price')->bigInteger()->nullable(); // null = inherit from variant
        $table->column('wholesale_price')->bigInteger()->nullable();
        $table->column('cost_price')->bigInteger()->nullable();
        $table->column('currency')->string(8)->defaultValue('VND');
        
        // Inventory (specific to this option)
        $table->column('stock_on_hand')->bigInteger()->defaultValue(0);
        $table->column('stock_reserved')->bigInteger()->defaultValue(0);
        $table->column('stock_available')->bigInteger()->defaultValue(0);
        $table->column('reorder_point')->integer()->defaultValue(0);
        $table->column('reorder_qty')->integer()->defaultValue(0);
        $table->column('location')->string(100)->nullable();
        $table->column('supplier_id')->string(64)->nullable();
        $table->column('supplier_sku')->string(64)->nullable();
        
        // Physical attributes (override variant/master)
        $table->column('weight')->integer()->nullable();
        $table->column('dimensions')->string(100)->nullable();
        $table->column('cover_image')->string(500)->nullable();
        $table->column('images')->text()->nullable(); // JSON array
        
        // Status
        $table->column('status')->string(32)->defaultValue('active'); // active, discontinued, out_of_stock
        $table->column('is_default')->boolean()->defaultValue(false); // default option for variant
        $table->column('sort_order')->integer()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'variant_id']);
        $table->index(['business_id', 'master_id']);
        $table->index(['business_id', 'sku']);
        $table->index(['business_id', 'barcode']);
        $table->index(['business_id', 'status']);
        $table->index(['business_id', 'binding_type']);
        $table->index(['business_id', 'condition']);
        $table->index(['business_id', 'format']);
        $table->index(['variant_id', 'is_default']);
        $table->save();
    }

    private function channelProducts(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'channel_products');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('variant_option_id')->bigInteger()->nullable(); // FK to product_variant_options (NEW)
        $table->column('variant_id')->bigInteger(); // FK to product_variants
        $table->column('master_id')->bigInteger(); // FK to product_masters (denormalized for queries)
        $table->column('sku')->string(64)->defaultValue('');
        $table->column('channel')->string(64); // e.g., 'shopee', 'tiktok_shop', 'lazada', 'website', 'bookpress', 'dropship_partner_x'
        $table->column('channel_account_id')->string(64)->nullable(); // specific shop/account on channel
        
        // Channel-specific overrides
        $table->column('title')->string(500)->nullable(); // custom title for channel
        $table->column('subtitle')->string(500)->nullable();
        $table->column('description')->longText()->nullable(); // custom description
        $table->column('description_short')->text()->nullable();
        $table->column('cover_image')->string(500)->nullable(); // channel-specific cover
        $table->column('images')->text()->nullable(); // JSON array of channel images
        $table->column('tags')->text()->nullable(); // JSON array
        $table->column('attributes')->text()->nullable(); // JSON key-value for channel-specific attrs
        
        // Channel pricing
        $table->column('sale_price')->bigInteger()->defaultValue(0); // giá bán ra trên kênh
        $table->column('compare_at_price')->bigInteger()->defaultValue(0); // giá gạch (MSRP)
        $table->column('promotion_price')->bigInteger()->nullable(); // giá khuyến mãi
        $table->column('promotion_start')->bigInteger()->nullable();
        $table->column('promotion_end')->bigInteger()->nullable();
        $table->column('currency')->string(8)->defaultValue('VND');
        
        // Channel inventory sync
        $table->column('channel_stock')->bigInteger()->defaultValue(0); // stock synced to channel
        $table->column('stock_sync_enabled')->boolean()->defaultValue(true);
        $table->column('last_synced_at')->bigInteger()->nullable();
        
        // Channel product identifiers
        $table->column('channel_product_id')->string(128)->nullable(); // product ID on the channel
        $table->column('channel_variant_id')->string(128)->nullable(); // variant ID on the channel
        $table->column('channel_category_id')->string(128)->nullable();
        $table->column('channel_url')->string(500)->nullable(); // product URL on channel
        
        // SEO for channel
        $table->column('slug')->string(600)->nullable();
        $table->column('meta_title')->string(255)->nullable();
        $table->column('meta_description')->text()->nullable();
        
        // Status & publishing
        $table->column('status')->string(32)->defaultValue('draft'); // draft, active, inactive, pending_review, rejected
        $table->column('publish_status')->string(32)->defaultValue('unpublished'); // unpublished, published, pending, failed
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('published_at')->bigInteger()->nullable();
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'variant_id']);
        $table->index(['business_id', 'master_id']);
        $table->index(['business_id', 'variant_option_id']);
        $table->index(['business_id', 'channel']);
        $table->index(['business_id', 'channel', 'status']);
        $table->index(['business_id', 'channel_account_id']);
        $table->index(['channel', 'channel_product_id'])->unique();
        $table->index(['variant_id', 'channel']);
        $table->index(['variant_option_id', 'channel']);
        $table->save();
    }

    /**
     * @param non-empty-string $name
     */
    private function table(DatabaseInterface $db, string $prefix, string $name): \Cycle\Database\Schema\AbstractTable
    {
        return $this->schema($db, $prefix . $name);
    }

    /**
     * @param non-empty-string $name
     */
    private function schema(DatabaseInterface $db, string $name): \Cycle\Database\Schema\AbstractTable
    {
        $table = $db->table($name);

        if (!$table instanceof \Cycle\Database\Table) {
            throw new \RuntimeException("Expected concrete Cycle table for {$name}, got " . $table::class);
        }

        return $table->getSchema();
    }
};