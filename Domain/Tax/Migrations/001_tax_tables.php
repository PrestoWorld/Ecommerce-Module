<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->taxRates($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = ['tax_rates'];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function taxRates(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'tax_rates');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('name')->string(255);
        $table->column('code')->string(64); // VAT, GST, SALES_TAX
        $table->column('rate')->float()->defaultValue(0); // percentage
        $table->column('type')->string(32)->defaultValue('percentage'); // percentage, fixed
        $table->column('scope')->string(32)->defaultValue('national'); // national, regional, local
        $table->column('region')->string(64)->nullable(); // province/state code
        $table->column('applicable_categories')->text()->defaultValue('[]'); // JSON array
        $table->column('exempt_categories')->text()->defaultValue('[]'); // JSON array
        $table->column('is_compound')->boolean()->defaultValue(false);
        $table->column('priority')->integer()->defaultValue(0);
        $table->column('is_active')->boolean()->defaultValue(true);
        $table->column('valid_from')->bigInteger()->defaultValue(0);
        $table->column('valid_until')->bigInteger()->nullable();
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'code']);
        $table->index(['business_id', 'is_active']);
        $table->index(['business_id', 'scope']);
        $table->index(['business_id', 'region']);
        $table->index(['business_id', 'priority']);
        $table->index(['valid_from', 'valid_until']);
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