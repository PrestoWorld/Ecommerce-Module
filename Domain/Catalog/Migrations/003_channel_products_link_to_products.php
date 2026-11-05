<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'channel_products';
        if (!$db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();
        if (!$schema->hasColumn('product_id')) {
            $schema->column('product_id')->bigInteger()->nullable();
        }
        try {
            $schema->index(['business_id', 'product_id'], ['name' => 'idx_cp_biz_product']);
        } catch (\Throwable $e) {
        }
        $schema->save();
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
    }
};
