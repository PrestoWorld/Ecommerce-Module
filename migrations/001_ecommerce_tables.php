<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->applications($db, $prefix);
        $this->accessTokens($db, $prefix);
        $this->rateLimits($db, $prefix);
        $this->webhookEvents($db, $prefix);
        $this->webhookOutbox($db, $prefix);
        $this->syncCursors($db, $prefix);
        $this->orders($db, $prefix);
        $this->products($db, $prefix);
        $this->customers($db, $prefix);
        $this->conversations($db, $prefix);
        $this->messages($db, $prefix);
        $this->pages($db, $prefix);
        $this->locations($db, $prefix);
        $this->carriers($db, $prefix);
        $this->shipments($db, $prefix);
        $this->affiliateProducts($db, $prefix);
        $this->affiliatePublishers($db, $prefix);
        $this->affiliateOrders($db, $prefix);
        $this->shareLinks($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = [
            'applications', 'access_tokens', 'rate_limits', 'webhook_events', 'webhook_outbox',
            'sync_cursors', 'orders', 'products', 'customers', 'conversations', 'messages',
            'pages', 'locations', 'carriers', 'shipments',
            'affiliate_products', 'affiliate_publishers', 'affiliate_orders', 'share_links',
        ];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function applications(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'applications');
        $table->primary('id');
        $table->column('app_id')->string(64);
        $table->column('secret_key')->string(255);
        $table->column('name')->string(255);
        $table->column('status')->integer()->defaultValue(1);
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->index(['app_id'])->unique();
        $table->save();
    }

    private function accessTokens(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'access_tokens');
        $table->primary('id');
        $table->column('app_id')->string(64);
        $table->column('business_id')->string(64);
        $table->column('token')->string(64);
        $table->column('expires_at')->bigInteger()->defaultValue(0);
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('last_used_at')->bigInteger()->nullable();
        $table->index(['app_id', 'business_id'])->unique();
        $table->save();
    }

    private function rateLimits(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'rate_limits');
        $table->primary('id');
        $table->column('bucket')->string(200);
        $table->column('url')->string(200);
        $table->column('window_start')->bigInteger()->defaultValue(0);
        $table->column('count')->integer()->defaultValue(0);
        $table->column('locked_until')->bigInteger()->defaultValue(0);
        $table->index(['bucket', 'url'])->unique();
        $table->save();
    }

    private function webhookEvents(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'webhook_events');
        $table->primary('id');
        $table->column('event')->string(64);
        $table->column('business_id')->string(64);
        $table->column('payload')->text();
        $table->column('received_at')->bigInteger()->defaultValue(0);
        $table->column('processed_at')->bigInteger()->nullable();
        $table->column('attempts')->integer()->defaultValue(0);
        $table->column('last_error')->text()->nullable();
        $table->index(['processed_at']);
        $table->save();
    }

    private function webhookOutbox(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'webhook_outbox');
        $table->primary('id');
        $table->column('event')->string(64);
        $table->column('business_id')->string(64);
        $table->column('payload')->text();
        $table->column('target_url')->string(255);
        $table->column('status')->string(16)->defaultValue('pending');
        $table->column('attempts')->integer()->defaultValue(0);
        $table->column('last_error')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->index(['status']);
        $table->save();
    }

    private function syncCursors(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'sync_cursors');
        $table->primary('id');
        $table->column('resource')->string(64);
        $table->column('cursor')->text();
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['resource'])->unique();
        $table->save();
    }

    private function orders(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'orders');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64);
        $table->column('status')->string(32);
        $table->column('source')->string(32)->defaultValue('');
        $table->column('customer_name')->string(255)->defaultValue('');
        $table->column('customer_mobile')->string(32)->defaultValue('');
        $table->column('total_amount')->bigInteger()->defaultValue(0);
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['status']);
        $table->index(['created_at']);
        $table->save();
    }

    private function products(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'products');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('sku')->string(64)->defaultValue('');
        $table->column('name')->string(255)->defaultValue('');
        $table->column('price')->bigInteger()->defaultValue(0);
        $table->column('stock')->bigInteger()->defaultValue(0);
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['sku']);
        $table->save();
    }

    private function customers(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'customers');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('code')->string(64)->defaultValue('');
        $table->column('name')->string(255)->defaultValue('');
        $table->column('mobile')->string(32)->defaultValue('');
        $table->column('email')->string(128)->defaultValue('');
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['mobile']);
        $table->save();
    }

    private function conversations(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'conversations');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('channel')->string(32)->defaultValue('');
        $table->column('page_id')->string(64)->defaultValue('');
        $table->column('customer_name')->string(255)->defaultValue('');
        $table->column('customer_id')->string(64)->defaultValue('');
        $table->column('last_message_at')->bigInteger()->nullable();
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->save();
    }

    private function messages(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'messages');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('conversation_external_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('direction')->string(16)->defaultValue('');
        $table->column('message_type')->string(32)->defaultValue('text');
        $table->column('content')->text();
        $table->column('sent_at')->bigInteger()->nullable();
        $table->column('payload')->text();
        $table->index(['business_id', 'conversation_external_id', 'external_id'])->unique();
        $table->save();
    }

    private function pages(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'pages');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('name')->string(255)->defaultValue('');
        $table->column('channel')->string(32)->defaultValue('');
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->save();
    }

    private function locations(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'locations');
        $table->primary('id');
        $table->column('version')->string(16);
        $table->column('type')->string(32);
        $table->column('location_id')->string(64);
        $table->column('parent_id')->bigInteger()->nullable();
        $table->column('name')->string(255);
        $table->column('other_name')->string(255)->nullable();
        $table->index(['version', 'type', 'location_id'])->unique();
        $table->index(['version', 'parent_id']);
        $table->save();
    }

    private function carriers(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'carriers');
        $table->primary('id');
        $table->column('branch')->string(16);
        $table->column('carrier_id')->bigInteger();
        $table->column('name')->string(255);
        $table->column('logo')->string(255)->nullable();
        $table->column('status')->integer()->defaultValue(1);
        $table->column('short_name')->string(64)->nullable();
        $table->column('services')->text()->nullable();
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['branch', 'carrier_id'])->unique();
        $table->save();
    }

    private function shipments(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'shipments');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('order_id')->bigInteger();
        $table->column('app_order_id')->string(64)->defaultValue('');
        $table->column('status')->string(32)->defaultValue('');
        $table->column('status_name')->string(64)->defaultValue('');
        $table->column('carrier_id')->bigInteger()->defaultValue(0);
        $table->column('carrier_service_id')->bigInteger()->defaultValue(0);
        $table->column('carrier_code')->string(64)->defaultValue('');
        $table->column('total_fee')->bigInteger()->defaultValue(0);
        $table->column('ship_fee')->bigInteger()->defaultValue(0);
        $table->column('customer_ship_fee')->bigInteger()->defaultValue(0);
        $table->column('total_cod')->bigInteger()->defaultValue(0);
        $table->column('send_carrier_error')->text()->nullable();
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'order_id'])->unique();
        $table->save();
    }

    private function affiliateProducts(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'affiliate_products');
        $table->primary('id');
        $table->column('channel')->string(32);
        $table->column('external_id')->string(64);
        $table->column('name')->string(255)->defaultValue('');
        $table->column('image')->string(255)->nullable();
        $table->column('price_min')->bigInteger()->defaultValue(0);
        $table->column('price_max')->bigInteger()->defaultValue(0);
        $table->column('commission_min')->bigInteger()->defaultValue(0);
        $table->column('commission_max')->bigInteger()->defaultValue(0);
        $table->column('shop_name')->string(255)->defaultValue('');
        $table->column('link_detail')->string(255)->nullable();
        $table->column('deep_link')->string(255)->nullable();
        $table->column('one_link')->string(255)->nullable();
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['channel', 'external_id'])->unique();
        $table->save();
    }

    private function affiliatePublishers(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'affiliate_publishers');
        $table->primary('id');
        $table->column('name')->string(255)->defaultValue('');
        $table->column('creator_id')->string(64)->defaultValue('');
        $table->column('status')->integer()->defaultValue(1);
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->save();
    }

    private function affiliateOrders(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'affiliate_orders');
        $table->primary('id');
        $table->column('publisher_id')->string(64)->defaultValue('');
        $table->column('publisher_name')->string(255)->defaultValue('');
        $table->column('partner_id')->string(64)->defaultValue('');
        $table->column('partner_name')->string(255)->defaultValue('');
        $table->column('custom_param')->string(48)->defaultValue('');
        $table->column('ecom_order_id')->string(64);
        $table->column('amount')->bigInteger()->defaultValue(0);
        $table->column('commission_amount')->bigInteger()->defaultValue(0);
        $table->column('commission_currency')->string(8)->defaultValue('');
        $table->column('status')->string(32)->defaultValue('');
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['ecom_order_id'])->unique();
        $table->index(['created_at']);
        $table->save();
    }

    private function shareLinks(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'share_links');
        $table->primary('id');
        $table->column('code')->string(64);
        $table->column('channel')->string(32)->defaultValue('');
        $table->column('product_external_id')->string(64)->defaultValue('');
        $table->column('publisher_id')->string(64)->defaultValue('');
        $table->column('custom_param')->string(48)->defaultValue('');
        $table->column('payload')->text();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->index(['code'])->unique();
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
     * DatabaseInterface only promises TableInterface, but schema editing lives on the
     * concrete Cycle Table — fail loudly if the runtime instance is anything else.
     *
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