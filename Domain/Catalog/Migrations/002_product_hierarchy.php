<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 002_product_hierarchy
 *
 * Catalog domain product tables (master/variant hierarchy):
 * - pw_product_masters (book-level product info / SPU)
 * - pw_product_variants (per-edition inventory / sellable units)
 *
 * Schema mirrors the columns used by ProductMasterRepository and
 * ProductVariantRepository.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->productMasters($db, $prefix);
        $this->productVariants($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        foreach (['product_variants', 'product_masters'] as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $table = $db->table($full);
                if ($table instanceof Table) {
                    $schema = $table->getSchema();
                    $schema->declareDropped();
                    $schema->save();
                }
            }
        }
    }

    private function productMasters(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_masters';
        if ($db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();
        $schema->primary('id');
        $schema->column('business_id')->string(64)->nullable(false);
        $schema->column('external_id')->string(64)->nullable(false);
        $schema->column('sku')->string(64)->nullable(false)->defaultValue('');
        $schema->column('title')->string(500)->nullable(false)->defaultValue('');
        $schema->column('subtitle')->string(500)->nullable();
        $schema->column('authors')->type('text')->nullable(); // JSON array
        $schema->column('translators')->type('text')->nullable(); // JSON array
        $schema->column('publisher')->string(255)->nullable();
        $schema->column('original_publisher')->string(255)->nullable();
        $schema->column('publication_year')->integer()->nullable();
        $schema->column('isbn_13')->string(20)->nullable();
        $schema->column('isbn_10')->string(20)->nullable();
        $schema->column('issn')->string(20)->nullable();
        $schema->column('qd_xb')->string(100)->nullable();
        $schema->column('xn_dk_xb')->string(100)->nullable();
        $schema->column('language')->string(50)->nullable(false)->defaultValue('vi');
        $schema->column('page_count')->integer()->nullable();
        $schema->column('dimensions')->string(100)->nullable();
        $schema->column('weight')->integer()->nullable();
        $schema->column('binding_type')->string(50)->nullable();
        $schema->column('cover_image')->string(500)->nullable();
        $schema->column('cover_images')->type('text')->nullable(); // JSON array
        $schema->column('table_of_contents')->type('text')->nullable();
        $schema->column('description')->type('longtext')->nullable();
        $schema->column('description_short')->type('text')->nullable();
        $schema->column('tags')->type('text')->nullable(); // JSON array
        $schema->column('categories')->type('text')->nullable(); // JSON array
        $schema->column('subject_codes')->type('text')->nullable(); // JSON array
        $schema->column('slug')->string(600)->nullable();
        $schema->column('meta_title')->string(255)->nullable();
        $schema->column('meta_description')->type('text')->nullable();
        $schema->column('meta_keywords')->string(500)->nullable();
        $schema->column('status')->string(32)->nullable(false)->defaultValue('draft'); // draft|active|archived
        $schema->column('payload')->type('longtext')->nullable(); // flexible JSON
        $schema->column('created_at')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('updated_at')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('published_at')->bigInteger()->nullable();
        $schema->index(['business_id', 'external_id'])->unique();
        $schema->index(['business_id', 'sku']);
        $schema->index(['business_id', 'status']);
        $schema->index(['isbn_13']);
        $schema->index(['slug']);
        $schema->save();
    }

    private function productVariants(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_variants';
        if ($db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();
        $schema->primary('id');
        $schema->column('business_id')->string(64)->nullable(false);
        $schema->column('external_id')->string(64)->nullable(false);
        $schema->column('master_id')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('sku')->string(64)->nullable(false)->defaultValue('');
        $schema->column('barcode')->string(64)->nullable();
        $schema->column('edition')->integer()->nullable(false)->defaultValue(1);
        $schema->column('edition_name')->string(255)->nullable();
        $schema->column('publication_year')->integer()->nullable();
        $schema->column('publisher')->string(255)->nullable();
        $schema->column('print_run')->integer()->nullable();
        $schema->column('print_date')->date()->nullable();
        $schema->column('cover_price')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('wholesale_price')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('cost_price')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('currency')->string(8)->nullable(false)->defaultValue('VND');
        $schema->column('stock_on_hand')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('stock_reserved')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('stock_available')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('reorder_point')->integer()->nullable(false)->defaultValue(0);
        $schema->column('reorder_qty')->integer()->nullable(false)->defaultValue(0);
        $schema->column('location')->string(255)->nullable();
        $schema->column('supplier_id')->string(64)->nullable();
        $schema->column('supplier_sku')->string(64)->nullable();
        $schema->column('weight')->integer()->nullable();
        $schema->column('dimensions')->string(100)->nullable();
        $schema->column('cover_image')->string(500)->nullable();
        $schema->column('status')->string(32)->nullable(false)->defaultValue('active');
        $schema->column('is_default')->boolean()->nullable(false)->defaultValue(false);
        $schema->column('payload')->type('longtext')->nullable(); // flexible JSON (regular/sale price, ...)
        $schema->column('created_at')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->column('updated_at')->bigInteger()->nullable(false)->defaultValue(0);
        $schema->index(['business_id', 'external_id'])->unique();
        $schema->index(['business_id', 'master_id']);
        $schema->index(['business_id', 'sku']);
        $schema->index(['business_id', 'status']);
        $schema->index(['master_id', 'is_default']);
        $schema->save();
    }
};
