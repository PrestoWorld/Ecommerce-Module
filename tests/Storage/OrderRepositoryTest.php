<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Pos\OrderRepository;

class OrderRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'orders', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('external_id', 128);
            $table->string('code', 64)->defaultValue('');
            $table->string('status', 32)->defaultValue('');
            $table->string('source', 64)->defaultValue('');
            $table->string('customer_name', 255)->defaultValue('');
            $table->string('customer_mobile', 32)->defaultValue('');
            $table->bigInteger('total_amount')->defaultValue(0);
            $table->text('payload');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['business_id', 'external_id']);
        });
    }

    private function repo(): OrderRepository
    {
        return new OrderRepository($this->db, self::PREFIX);
    }

    public function test_save_inserts_and_returns_decoded_payload(): void
    {
        $saved = $this->repo()->save('biz1', [
            'external_id' => 'E1',
            'code' => 'DH-001',
            'status' => 'FULFILLED',
            'source' => 'Shopee',
            'customer_name' => 'Lan',
            'customer_mobile' => '0901',
            'total_amount' => 150000,
            'payload' => ['id' => 'E1', 'items' => [1, 2]],
        ]);

        $this->assertSame('E1', $saved['external_id']);
        $this->assertSame(['id' => 'E1', 'items' => [1, 2]], $saved['payload']);
        $this->assertIsInt($saved['id']);
    }

    public function test_save_upserts_by_external_id(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'E1', 'code' => 'DH-001', 'status' => 'NEW']);
        $repo->save('biz1', ['external_id' => 'E1', 'code' => 'DH-001', 'status' => 'CONFIRMED']);

        $found = $repo->find('biz1', 'E1');

        $this->assertSame('CONFIRMED', $found['status']);
        $this->assertCount(1, $repo->search('biz1', [], 0, 100)['items']);
    }

    public function test_find_returns_null_when_missing(): void
    {
        $this->assertNull($this->repo()->find('biz1', 'NOPE'));
    }

    public function test_search_filters_and_sorts(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'E1', 'code' => 'DH-001', 'status' => 'NEW', 'customer_name' => 'Lan', 'created_at' => 100, 'total_amount' => 10]);
        $repo->save('biz1', ['external_id' => 'E2', 'code' => 'DH-002', 'status' => 'FULFILLED', 'customer_name' => 'Hoa', 'created_at' => 200, 'total_amount' => 20]);
        $repo->save('biz2', ['external_id' => 'E3', 'code' => 'DH-003', 'status' => 'NEW', 'customer_name' => 'An', 'created_at' => 300, 'total_amount' => 30]);

        $status = $repo->search('biz1', ['status' => 'NEW'], 0, 100);
        $this->assertCount(1, $status['items']);
        $this->assertSame(1, $status['total']);
        $this->assertSame('E1', $status['items'][0]['external_id']);

        $keyword = $repo->search('biz1', ['keyword' => 'Dh-002'], 0, 100);
        $this->assertSame('E2', $keyword['items'][0]['external_id']);

        $ordered = $repo->search('biz1', [], 0, 100, ['createdAt' => 'asc']);
        $this->assertSame('E1', $ordered['items'][0]['external_id']);
        $this->assertSame('E2', $ordered['items'][1]['external_id']);

        $scoped = $repo->search('biz1', [], 0, 100);
        $this->assertSame(2, $scoped['total']);
        $this->assertCount(2, $scoped['items']);
    }

    public function test_search_date_range(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'E1', 'created_at' => 1000]);
        $repo->save('biz1', ['external_id' => 'E2', 'created_at' => 2000]);

        $result = $repo->search('biz1', ['createdAtFrom' => 1500], 0, 100);

        $this->assertSame(1, $result['total']);
        $this->assertSame('E2', $result['items'][0]['external_id']);
    }

    public function test_pagination_with_offset_and_limit(): void
    {
        $repo = $this->repo();
        foreach (range(1, 5) as $i) {
            $repo->save('biz1', ['external_id' => 'E' . $i, 'created_at' => $i]);
        }

        $page = $repo->search('biz1', [], 2, 2);

        $this->assertSame(2, count($page['items']));
        $this->assertSame(5, $page['total']);
    }

    public function test_sources_distinct(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'E1', 'source' => 'Shopee']);
        $repo->save('biz1', ['external_id' => 'E2', 'source' => 'Lazada']);
        $repo->save('biz1', ['external_id' => 'E3', 'source' => 'Shopee']);
        $repo->save('biz1', ['external_id' => 'E4', 'source' => '']);

        $this->assertSame(['Shopee', 'Lazada'], $repo->sources('biz1'));
    }

    public function test_next_internal_id(): void
    {
        $repo = $this->repo();
        $this->assertSame(1, $repo->nextInternalId('biz1'));

        $repo->save('biz1', ['external_id' => 'E1']);

        $this->assertSame(2, $repo->nextInternalId('biz1'));
    }

    public function test_save_generates_internal_id_when_none_given(): void
    {
        $saved = $this->repo()->save('biz1', ['status' => 'NEW']);

        $this->assertSame('1', $saved['external_id']);
    }
}