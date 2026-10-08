<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->orders($db, $prefix);
        $this->carts($db, $prefix);
        $this->quotes($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = ['quotes', 'carts', 'orders'];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function orders(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'orders');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64);
        $table->column('customer_id')->string(64);
        $table->column('customer_external_id')->string(64);
        $table->column('status')->string(32)->defaultValue('draft'); // draft, pending, confirmed, processing, shipped, delivered, cancelled, returned, refunded
        $table->column('source')->string(32)->defaultValue('manual'); // website, pos, shopee, tiktok_shop, lazada, bookpress, manual
        $table->column('channel_order_id')->string(128)->nullable();
        $table->column('items')->text(); // JSON array
        $table->column('subtotal')->bigInteger()->defaultValue(0);
        $table->column('tax_total')->bigInteger()->defaultValue(0);
        $table->column('discount_total')->bigInteger()->defaultValue(0);
        $table->column('shipping_fee')->bigInteger()->defaultValue(0);
        $table->column('total')->bigInteger()->defaultValue(0);
        $table->column('billing_address')->text()->nullable(); // JSON
        $table->column('shipping_address')->text()->nullable(); // JSON
        $table->column('customer_note')->text()->nullable();
        $table->column('internal_note')->text()->nullable();
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('confirmed_at')->bigInteger()->nullable();
        $table->column('shipped_at')->bigInteger()->nullable();
        $table->column('delivered_at')->bigInteger()->nullable();
        $table->column('cancelled_at')->bigInteger()->nullable();
        $table->column('cancelled_reason')->string(500)->nullable();

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'code'])->unique();
        $table->index(['business_id', 'customer_external_id']);
        $table->index(['business_id', 'status']);
        $table->index(['business_id', 'source']);
        $table->index(['business_id', 'channel_order_id']);
        $table->index(['business_id', 'created_at']);
        $table->save();
    }

    private function carts(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'carts');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('customer_external_id')->string(64);
        $table->column('items')->text()->defaultValue('[]'); // JSON array
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('expires_at')->bigInteger()->nullable();

        $table->index(['business_id', 'customer_external_id'])->unique();
        $table->index(['expires_at']);
        $table->save();
    }

    private function quotes(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'quotes');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64);
        $table->column('customer_id')->string(64);
        $table->column('customer_external_id')->string(64);
        $table->column('status')->string(32)->defaultValue('draft'); // draft, sent, accepted, rejected, expired, converted
        $table->column('items')->text(); // JSON array
        $table->column('subtotal')->bigInteger()->defaultValue(0);
        $table->column('tax_total')->bigInteger()->defaultValue(0);
        $table->column('discount_total')->bigInteger()->defaultValue(0);
        $table->column('shipping_fee')->bigInteger()->defaultValue(0);
        $table->column('total')->bigInteger()->defaultValue(0);
        $table->column('valid_until')->bigInteger()->nullable();
        $table->column('notes')->text()->nullable();
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('converted_at')->bigInteger()->nullable();
        $table->column('converted_order_id')->string(64)->nullable();

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'code'])->unique();
        $table->index(['business_id', 'customer_external_id']);
        $table->index(['business_id', 'status']);
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