<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 003_ecommerce_billing_tables
 *
 * Billing / marketplace tables that were added to 001_ecommerce_tables after
 * that migration had already run from an older published copy. Existing
 * installations therefore have the migration marked as executed but lack
 * these tables; fresh installations create them in 001 and this migration is
 * a no-op.
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->bills($db, $prefix);
        $this->billItems($db, $prefix);
        $this->draftBills($db, $prefix);
        $this->ecomShops($db, $prefix);
        $this->ecomReturns($db, $prefix);
        $this->invoices($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        foreach ([
            'invoices', 'ecom_returns', 'ecom_shops',
            'draft_bills', 'bill_items', 'bills',
        ] as $name) {
            if (!$db->hasTable($prefix . $name)) {
                continue;
            }

            $db->table($prefix . $name)->getSchema()->drop();
        }
    }

    private function bills(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'bills');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64)->defaultValue('');
        $table->column('type')->integer()->defaultValue(1);
        $table->column('status')->integer()->defaultValue(1);
        $table->column('customer_id')->integer()->defaultValue(0);
        $table->column('customer_name')->string(255)->defaultValue('');
        $table->column('customer_mobile')->string(32)->defaultValue('');
        $table->column('total_amount')->bigInteger()->defaultValue(0);
        $table->column('note')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['business_id']);
        $table->index(['external_id']);
        $table->index(['code']);
        $table->index(['status']);
        $table->save();
    }

    private function billItems(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'bill_items');
        $table->primary('id');
        $table->column('bill_id')->integer()->defaultValue(0);
        $table->column('product_id')->integer()->defaultValue(0);
        $table->column('product_code')->string(64)->defaultValue('');
        $table->column('product_name')->string(255)->defaultValue('');
        $table->column('quantity')->integer()->defaultValue(0);
        $table->column('price')->bigInteger()->defaultValue(0);
        $table->column('amount')->bigInteger()->defaultValue(0);
        $table->column('unit')->string(32)->defaultValue('');
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['bill_id']);
        $table->index(['product_id']);
        $table->save();
    }

    private function draftBills(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'draft_bills');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('code')->string(64)->defaultValue('');
        $table->column('status')->integer()->defaultValue(1);
        $table->column('total_amount')->bigInteger()->defaultValue(0);
        $table->column('note')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['business_id']);
        $table->index(['code']);
        $table->index(['status']);
        $table->save();
    }

    private function ecomShops(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'ecom_shops');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('shop_id')->string(64)->defaultValue('');
        $table->column('name')->string(255)->defaultValue('');
        $table->column('channel')->string(32)->defaultValue('');
        $table->column('status')->integer()->defaultValue(1);
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['business_id']);
        $table->index(['shop_id']);
        $table->index(['channel']);
        $table->save();
    }

    private function ecomReturns(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'ecom_returns');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64)->defaultValue('');
        $table->column('order_id')->integer()->defaultValue(0);
        $table->column('status')->integer()->defaultValue(1);
        $table->column('customer_name')->string(255)->defaultValue('');
        $table->column('customer_mobile')->string(32)->defaultValue('');
        $table->column('total_amount')->bigInteger()->defaultValue(0);
        $table->column('note')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['business_id']);
        $table->index(['external_id']);
        $table->index(['status']);
        $table->save();
    }

    private function invoices(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'invoices');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64)->defaultValue('');
        $table->column('depot_id')->integer()->defaultValue(0);
        $table->column('reference_item_id')->string(64)->defaultValue('');
        $table->column('tax_type')->integer()->defaultValue(0);
        $table->column('tax_invoice_number')->string(64)->defaultValue('');
        $table->column('tax_status')->integer()->defaultValue(0);
        $table->column('tax_status_name')->string(128)->defaultValue('');
        $table->column('error_code')->string(64)->defaultValue('');
        $table->column('error_message')->text()->nullable();
        $table->column('buyer_name')->string(255)->defaultValue('');
        $table->column('buyer_mobile')->string(32)->defaultValue('');
        $table->column('buyer_address')->text()->nullable();
        $table->column('buyer_tax_code')->string(64)->defaultValue('');
        $table->column('buyer_email')->string(128)->defaultValue('');
        $table->column('buyer_company')->string(255)->defaultValue('');
        $table->column('total_amount')->bigInteger()->defaultValue(0);
        $table->column('invoice_number')->string(64)->defaultValue('');
        $table->column('invoice_date')->string(32)->defaultValue('');
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->index(['business_id']);
        $table->index(['external_id']);
        $table->index(['tax_status']);
        $table->save();
    }

    private function table(DatabaseInterface $db, string $prefix, string $name): \Cycle\Database\Schema\AbstractTable
    {
        $table = $db->table($prefix . $name);

        if (!$table instanceof Table) {
            throw new \RuntimeException(sprintf(
                'Expected concrete Cycle table for %s, got %s',
                $prefix . $name,
                $table::class,
            ));
        }

        return $table->getSchema();
    }
};
