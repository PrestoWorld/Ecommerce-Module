<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 002_stock_tables
 *
 * Creates the stock balance + journal tables used by the Ecommerce stock
 * driver (pw_stock_items / pw_stock_movements). Locations are intentionally
 * NOT created here: the shared `locations` table already exists in this
 * installation with a different (shipping) schema.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->stockItems($db, $prefix);
        $this->stockMovements($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        foreach (['stock_movements', 'stock_items'] as $name) {
            $full = $prefix . $name;
            if (!$db->hasTable($full)) {
                continue;
            }
            $schema = $this->schema($db, $full);
            $schema->declareDropped();
            $schema->save();
        }
    }

    private function stockItems(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'stock_items';
        if ($db->hasTable($table)) {
            return;
        }

        $schema = $this->schema($db, $table);
        $schema->primary('id');
        $schema->column('business_id')->string(64);
        $schema->column('external_id')->string(64);
        $schema->column('variant_option_external_id')->string(64);
        $schema->column('location_id')->string(64);
        $schema->column('location_code')->string(64);
        $schema->column('quantity_on_hand')->bigInteger()->defaultValue(0);
        $schema->column('quantity_reserved')->bigInteger()->defaultValue(0);
        $schema->column('quantity_available')->bigInteger()->defaultValue(0);
        $schema->column('reorder_point')->integer()->defaultValue(0);
        $schema->column('reorder_quantity')->integer()->defaultValue(0);
        $schema->column('cost_price')->bigInteger()->nullable();
        $schema->column('average_cost')->bigInteger()->nullable();
        $schema->column('status')->string(32)->defaultValue('active');
        $schema->column('metadata')->text()->nullable();
        $schema->column('created_at')->bigInteger()->defaultValue(0);
        $schema->column('updated_at')->bigInteger()->defaultValue(0);
        $schema->column('last_counted_at')->bigInteger()->nullable();

        $schema->index(['business_id', 'external_id'])->unique();
        $schema->index(['business_id', 'variant_option_external_id']);
        $schema->index(['business_id', 'location_id']);
        $schema->index(['business_id', 'status']);
        $schema->index(['business_id', 'variant_option_external_id', 'location_id'])->unique();
        $schema->save();
    }

    private function stockMovements(DatabaseInterface $db, string $prefix): void
    {
        $table = $prefix . 'stock_movements';
        if ($db->hasTable($table)) {
            return;
        }

        $schema = $this->schema($db, $table);
        $schema->primary('id');
        $schema->column('business_id')->string(64);
        $schema->column('external_id')->string(64);
        $schema->column('stock_item_external_id')->string(64);
        $schema->column('variant_option_external_id')->string(64);
        $schema->column('location_id')->string(64);
        $schema->column('type')->string(32);
        $schema->column('quantity_change')->bigInteger()->defaultValue(0);
        $schema->column('quantity_before')->bigInteger()->defaultValue(0);
        $schema->column('quantity_after')->bigInteger()->defaultValue(0);
        $schema->column('reason')->string(500);
        $schema->column('reference_id')->string(64)->nullable();
        $schema->column('reference_type')->string(32)->nullable();
        $schema->column('metadata')->text()->nullable();
        $schema->column('created_at')->bigInteger()->defaultValue(0);

        $schema->index(['business_id', 'external_id'])->unique();
        $schema->index(['business_id', 'variant_option_external_id']);
        $schema->index(['business_id', 'location_id']);
        $schema->index(['business_id', 'type']);
        $schema->index(['business_id', 'reference_id']);
        $schema->index(['business_id', 'created_at']);
        $schema->save();
    }

    private function schema(DatabaseInterface $db, string $name): \Cycle\Database\Schema\AbstractTable
    {
        $table = $db->table($name);

        if (!$table instanceof Table) {
            throw new \RuntimeException("Expected concrete Cycle table for {$name}, got " . $table::class);
        }

        return $table->getSchema();
    }
};
