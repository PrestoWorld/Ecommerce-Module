<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 004_audit_flags
 *
 * Adds ai_audit & manual_audit flags to catalog tables.
 * Both default to false. manual_audit takes precedence: when true, the record
 * is excluded from AI tools and is reserved for manual editing only.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->addAuditColumns($db, $prefix, 'product_masters');
        $this->addAuditColumns($db, $prefix, 'product_variants');
        $this->addAuditColumns($db, $prefix, 'channel_products');
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        foreach (['product_masters', 'product_variants', 'channel_products'] as $name) {
            $full = $prefix . $name;
            if (!$db->hasTable($full)) {
                continue;
            }
            $table = $db->table($full);
            if (!$table instanceof Table) {
                continue;
            }
            $schema = $table->getSchema();
            foreach (['ai_audit', 'manual_audit'] as $column) {
                if ($schema->hasColumn($column)) {
                    $schema->declareDropped($column);
                }
            }
            $schema->save();
        }
    }

    private function addAuditColumns(DatabaseInterface $db, string $prefix, string $tableName): void
    {
        $full = $prefix . $tableName;
        if (!$db->hasTable($full)) {
            return;
        }

        $table = $db->table($full);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();

        if (!$schema->hasColumn('ai_audit')) {
            $schema->column('ai_audit')->boolean()->nullable(false)->defaultValue(false);
        }

        if (!$schema->hasColumn('manual_audit')) {
            $schema->column('manual_audit')->boolean()->nullable(false)->defaultValue(false);
        }

        $schema->save();
    }
};