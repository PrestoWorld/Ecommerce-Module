<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Pos\CustomerRepository;

class CustomerRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'customers', static function ($table): void {
            $table->string('business_id', 64);
            $table->string('external_id', 128);
            $table->string('code', 64)->defaultValue('');
            $table->string('name', 255)->defaultValue('');
            $table->string('mobile', 32)->defaultValue('');
            $table->string('email', 255)->defaultValue('');
            $table->text('payload');
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['business_id', 'external_id']);
        });
    }

    private function repo(): CustomerRepository
    {
        return new CustomerRepository($this->db, self::PREFIX);
    }

    public function test_save_and_search(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', [
            'external_id' => 'C1',
            'code' => 'KH01',
            'name' => 'Lan',
            'mobile' => '0901',
            'email' => 'lan@example.com',
            'payload' => ['id' => 'C1'],
        ]);
        $repo->save('biz1', ['external_id' => 'C2', 'name' => 'Hoa', 'mobile' => '0902']);
        $repo->save('biz2', ['external_id' => 'C3', 'name' => 'Lan', 'mobile' => '0903']);

        $found = $repo->search('biz1', ['keyword' => 'kh01'], 0, 100);
        $this->assertSame('C1', $found['items'][0]['external_id']);

        $lan = $repo->search('biz2', ['keyword' => 'lan'], 0, 100);
        $this->assertSame('C3', $lan['items'][0]['external_id']);

        $this->assertSame(2, $repo->search('biz1', [], 0, 100)['total']);
    }

    public function test_find(): void
    {
        $repo = $this->repo();
        $repo->save('biz1', ['external_id' => 'C1', 'name' => 'Lan']);

        $this->assertSame('Lan', $repo->find('biz1', 'C1')['name']);
        $this->assertNull($repo->find('biz1', 'MISSING'));
    }
}