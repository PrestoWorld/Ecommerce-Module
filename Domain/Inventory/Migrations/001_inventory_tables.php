<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->locations($db, $prefix);
        $this->stockItems($db, $prefix);
        $this->stockMovements($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = ['stock_movements', 'stock_items', 'locations'];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function locations(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'locations');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64);
        $table->column('name')->string(255);
        $table->column('type')->string(32)->defaultValue('warehouse'); // warehouse, store, dropship, virtual
        $table->column('address')->text()->nullable(); // JSON
        $table->column('contact_name')->string(255)->nullable();
        $table->column('contact_phone')->string(32)->nullable();
        $table->column('contact_email')->string(128)->nullable();
        $table->column('is_active')->boolean()->defaultValue(true);
        $table->column('is_default')->boolean()->defaultValue(false);
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'code'])->unique();
        $table->index(['business_id', 'type']);
        $table->index(['business_id', 'is_active']);
        $table->index(['business_id', 'is_default']);
        $table->save();
    }

    private function stockItems(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'stock_items');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('variant_option_external_id')->string(64);
        $table->column('location_id')->string(64);
        $table->column('location_code')->string(64);
        $table->column('quantity_on_hand')->bigInteger()->defaultValue(0);
        $table->column('quantity_reserved')->bigInteger()->defaultValue(0);
        $table->column('quantity_available')->bigInteger()->defaultValue(0);
        $table->column('reorder_point')->integer()->defaultValue(0);
        $table->column('reorder_quantity')->integer()->defaultValue(0);
        $table->column('cost_price')->bigInteger()->nullable();
        $table->column('average_cost')->bigInteger()->nullable();
        $table->column('status')->string(32)->defaultValue('active'); // active, low_stock, out_of_stock, discontinued
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('last_counted_at')->bigInteger()->nullable();

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'variant_option_external_id']);
        $table->index(['business_id', 'location_id']);
        $table->index(['business_id', 'status']);
        $table->index(['variant_option_external_id', 'location_id'])->unique();
        $table->save();
    }

    private function stockMovements(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'stock_movements');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('stock_item_external_id')->string(64);
        $table->column('variant_option_external_id')->string(64);
        $table->column('location_id')->string(64);
        $table->column('type')->string(32); // adjustment, reservation, release, transfer_in, transfer_out, receipt, count
        $table->column('quantity_change')->bigInteger()->defaultValue(0);
        $table->column('quantity_before')->bigInteger()->defaultValue(0);
        $table->column('quantity_after')->bigInteger()->defaultValue(0);
        $table->column('reason')->string(500);
        $table->column('reference_id')->string(64)->nullable();
        $table->column('reference_type')->string(32)->nullable(); // order, reservation, transfer, manual, po
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'variant_option_external_id']);
        $table->index(['business_id', 'location_id']);
        $table->index(['business_id', 'type']);
        $table->index(['business_id', 'reference_id']);
        $table->index(['business_id', 'created_at']);
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