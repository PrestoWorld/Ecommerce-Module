<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Core\Uuid;
use PrestoWorld\Database\MigrationInterface;

/**
 * 005_product_master_uuid
 *
 * Adds a `uuid` column to bow_product_masters and backfills every existing
 * row with a deterministic UUID v5 (namespace = business_id, name =
 * external_id). The admin products screen uses this uuid as the public id so
 * URLs never carry Vietnamese diacritics; external_id is preserved as the
 * source link (crawler / Nhanh.vn).
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_masters';
        if (!$db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();

        if (!$schema->hasColumn('uuid')) {
            $schema->column('uuid')->string(36)->nullable();
        }

        try {
            $schema->index(['uuid'], ['name' => 'uq_pm_uuid'])->unique();
        } catch (\Throwable) {
        }

        $schema->save();

        // Backfill: deterministic v5 so re-runs are stable and idempotent.
        $rows = $db->select('id', 'business_id', 'external_id')
            ->from($name)
            ->where('uuid', null)
            ->run()
            ->fetchAll();

        foreach ($rows as $row) {
            $row = (array) $row;
            $businessId = (string) ($row['business_id'] ?? '');
            $externalId = (string) ($row['external_id'] ?? '');
            $uuid = Uuid::v5(Uuid::NS_URL, $businessId . '|' . $externalId);

            $db->update($name, ['uuid' => $uuid], ['id' => (int) $row['id']])->run();
        }
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_masters';
        if (!$db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();

        if ($schema->hasColumn('uuid')) {
            $schema->declareDropped('uuid');
        }

        $schema->save();
    }
};