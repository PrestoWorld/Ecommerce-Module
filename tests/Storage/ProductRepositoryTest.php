<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Pos\ProductRepository;

class ProductRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'products', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('external_id', 128);
            $table->string('sku', 64)->defaultValue('');
            $table->string('name', 255)->defaultValue('');
            $table->bigInteger('price')->defaultValue(0);
            $table->bigInteger('stock')->defaultValue(0);
            $table->text('payload');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['business_id', 'external_id']);
        });
    }

    private function repo(): ProductRepository
    {
        return new ProductRepository($this->db, self::PREFIX);
    }

    public function test_save_and_find(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', [
            'external_id' => 'P1',
            'sku' => 'SKU-A',
            'name' => 'Cap',
            'price' => 99000,
            'stock' => 5,
            'payload' => ['id' => 'P1', 'name' => 'Cap'],
        ]);

        $found = $repo->find('biz1', 'P1');

        $this->assertSame('P1', $found['external_id']);
        $this->assertSame('SKU-A', $found['sku']);
        $this->assertSame(['id' => 'P1', 'name' => 'Cap'], $found['payload']);
    }

    public function test_save_upserts(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'P1', 'name' => 'Old', 'payload' => []]);
        $repo->save('biz1', ['external_id' => 'P1', 'name' => 'New', 'payload' => []]);

        $this->assertSame('New', $repo->find('biz1', 'P1')['name']);
        $this->assertCount(1, $repo->search('biz1', [], 0, 100)['items']);
    }

    public function test_search_keyword_and_category_prefix(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'P1', 'sku' => 'AO-01', 'name' => 'Ao so mi']);
        $repo->save('biz1', ['external_id' => 'P2', 'sku' => 'QUAN-01', 'name' => 'Quan jean']);
        $repo->save('biz2', ['external_id' => 'P3', 'sku' => 'AO-02', 'name' => 'Ao khoac']);

        $found = $repo->search('biz1', ['keyword' => 'ao'], 0, 100);
        $this->assertSame(1, $found['total']);
        $this->assertSame('P1', $found['items'][0]['external_id']);

        $categorized = $repo->search('biz1', ['category' => 'AO'], 0, 100);
        $this->assertSame('P1', $categorized['items'][0]['external_id']);

        $scoped = $repo->search('biz1', [], 0, 100);
        $this->assertSame(2, $scoped['total']);
        $this->assertCount(2, $scoped['items']);
    }

    public function test_sort_by_price(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'P1', 'name' => 'A', 'price' => 10]);
        $repo->save('biz1', ['external_id' => 'P2', 'name' => 'B', 'price' => 30]);
        $repo->save('biz1', ['external_id' => 'P3', 'name' => 'C', 'price' => 20]);

        $asc = $repo->search('biz1', [], 0, 100, ['price' => 'asc']);

        $this->assertSame(['P1', 'P3', 'P2'], array_column($asc['items'], 'external_id'));
    }

    public function test_pagination(): void
    {
        $repo = $this->repo();
        foreach (range(1, 4) as $i) {
            $repo->save('biz1', ['external_id' => 'P' . $i]);
        }

        $page = $repo->search('biz1', [], 1, 2);

        $this->assertCount(2, $page['items']);
        $this->assertSame(4, $page['total']);
    }
}