<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->products($db, $prefix);
        $this->productVariants($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = ['product_variants', 'products'];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function products(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'products');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('sku')->string(64)->defaultValue('');
        
        // Core product information (SPU - Standard Product Unit)
        $table->column('name')->string(500)->defaultValue('');
        $table->column('description')->longText()->nullable();
        $table->column('short_description')->text()->nullable();
        
        // Book-specific fields
        $table->column('isbn_13')->string(20)->nullable();
        $table->column('isbn_10')->string(20)->nullable();
        $table->column('issn')->string(20)->nullable();
        $table->column('authors')->text()->nullable(); // JSON array
        $table->column('publisher')->string(255)->nullable();
        $table->column('publication_year')->integer()->nullable();
        $table->column('page_count')->integer()->nullable();
        $table->column('language')->string(50)->defaultValue('vi');
        $table->column('dimensions')->string(100)->nullable();
        $table->column('weight')->integer()->nullable();
        $table->column('binding_type')->string(50)->nullable();
        
        // Media
        $table->column('cover_image')->string(500)->nullable();
        $table->column('images')->text()->nullable(); // JSON array
        
        // Categorization
        $table->column('categories')->text()->nullable(); // JSON array
        $table->column('tags')->text()->nullable(); // JSON array
        
        // SEO
        $table->column('slug')->string(600)->nullable();
        $table->column('meta_title')->string(255)->nullable();
        $table->column('meta_description')->text()->nullable();
        
        // Status
        $table->column('status')->string(32)->defaultValue('draft'); // draft, active, archived
        $table->column('payload')->text()->nullable(); // flexible JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('published_at')->bigInteger()->nullable();
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'sku']);
        $table->index(['business_id', 'status']);
        $table->index(['isbn_13']);
        $table->save();
    }

    private function productVariants(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'product_variants');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('product_id')->bigInteger(); // FK to products
        $table->column('product_external_id')->string(64); // denormalized
        $table->column('sku')->string(64)->defaultValue('');
        $table->column('barcode')->string(64)->nullable();
        
        // Variant attributes (defines the variation)
        $table->column('attributes')->text()->nullable(); // JSON: {color: "black", storage: "128GB"} or {binding: "hardcover", condition: "new"}
        
        // Pricing
        $table->column('price')->bigInteger()->defaultValue(0); // sale price
        $table->column('compare_at_price')->bigInteger()->defaultValue(0); // MSRP
        $table->column('cost_price')->bigInteger()->defaultValue(0);
        $table->column('currency')->string(8)->defaultValue('VND');
        
        // Inventory
        $table->column('stock')->bigInteger()->defaultValue(0);
        $table->column('reserved_stock')->bigInteger()->defaultValue(0);
        $table->column('available_stock')->bigInteger()->defaultValue(0);
        $table->column('low_stock_threshold')->integer()->defaultValue(0);
        $table->column('track_inventory')->boolean()->defaultValue(true);
        
        // Physical (may override product)
        $table->column('weight')->integer()->nullable();
        $table->column('dimensions')->string(100)->nullable();
        $table->column('cover_image')->string(500)->nullable();
        
        // Status
        $table->column('status')->string(32)->defaultValue('active'); // active, inactive, discontinued
        $table->column('is_default')->boolean()->defaultValue(false);
        $table->column('position')->integer()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'product_id']);
        $table->index(['business_id', 'sku']);
        $table->index(['business_id', 'barcode']);
        $table->index(['business_id', 'status']);
        $table->index(['product_id', 'is_default']);
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