<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->invoices($db, $prefix);
        $this->payments($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = ['payments', 'invoices'];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function invoices(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'invoices');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('number')->string(64);
        $table->column('order_id')->string(64);
        $table->column('order_external_id')->string(64);
        $table->column('customer_id')->string(64);
        $table->column('customer_external_id')->string(64);
        $table->column('type')->string(32)->defaultValue('sale'); // sale, refund, credit_note, debit_note
        $table->column('status')->string(32)->defaultValue('draft'); // draft, issued, paid, partially_paid, overdue, cancelled, refunded
        $table->column('items')->text(); // JSON array
        $table->column('payments')->text(); // JSON array
        $table->column('subtotal')->bigInteger()->defaultValue(0);
        $table->column('tax_total')->bigInteger()->defaultValue(0);
        $table->column('discount_total')->bigInteger()->defaultValue(0);
        $table->column('total')->bigInteger()->defaultValue(0);
        $table->column('paid_amount')->bigInteger()->defaultValue(0);
        $table->column('balance_due')->bigInteger()->defaultValue(0);
        $table->column('currency')->string(8)->defaultValue('VND');
        $table->column('issue_date')->bigInteger()->defaultValue(0);
        $table->column('due_date')->bigInteger()->defaultValue(0);
        $table->column('paid_at')->bigInteger()->nullable();
        $table->column('notes')->text()->nullable();
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'number'])->unique();
        $table->index(['business_id', 'order_external_id']);
        $table->index(['business_id', 'customer_external_id']);
        $table->index(['business_id', 'status']);
        $table->index(['business_id', 'type']);
        $table->index(['business_id', 'issue_date']);
        $table->index(['business_id', 'due_date']);
        $table->save();
    }

    private function payments(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'payments');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('invoice_id')->string(64);
        $table->column('invoice_external_id')->string(64);
        $table->column('number')->string(64);
        $table->column('amount')->bigInteger()->defaultValue(0);
        $table->column('currency')->string(8)->defaultValue('VND');
        $table->column('method')->string(32); // cash, card, bank_transfer, momo, zalopay, vnpay, cod, wallet
        $table->column('gateway')->string(64); // gateway used
        $table->column('status')->string(32)->defaultValue('pending'); // pending, processing, success, failed, refunded, cancelled
        $table->column('transaction_id')->string(128)->nullable();
        $table->column('gateway_transaction_id')->string(128)->nullable();
        $table->column('gateway_response')->text()->nullable();
        $table->column('paid_by')->string(32)->nullable(); // customer, admin, system
        $table->column('paid_by_id')->string(64)->nullable();
        $table->column('metadata')->text()->nullable(); // JSON
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->column('completed_at')->bigInteger()->nullable();

        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'number'])->unique();
        $table->index(['business_id', 'invoice_external_id']);
        $table->index(['business_id', 'status']);
        $table->index(['business_id', 'method']);
        $table->index(['business_id', 'gateway']);
        $table->index(['business_id', 'transaction_id']);
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