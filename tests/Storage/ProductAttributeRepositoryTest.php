<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage\ProductAttributeRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage\ProductAttributeValueRepository;

class ProductAttributeRepositoryTest extends RepositoryTestCase
{
    private const BUSINESS = 'biz-1';

    private ProductAttributeRepository $attributes;
    private ProductAttributeValueRepository $values;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 2) . '/Domain/Attribute/Migrations/001_attribute_tables.php';
        $migration->up($this->db, self::PREFIX);

        $this->attributes = new ProductAttributeRepository($this->db, self::PREFIX);
        $this->values = new ProductAttributeValueRepository($this->db, self::PREFIX);
    }

    public function testSaveAndFindDefinitionUsesUuidPk(): void
    {
        $saved = $this->attributes->save(self::BUSINESS, [
            'code' => 'condition',
            'name' => 'Tình trạng',
            'type' => 'condition',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $saved['id']);

        $found = $this->attributes->find(self::BUSINESS, 'condition');
        $this->assertNotNull($found);
        $this->assertSame('Tình trạng', $found['name']);
        $this->assertSame('condition', $found['type']);
        $this->assertSame('active', $found['status']);
        $this->assertTrue((bool) $found['is_combinable']);

        $this->assertNull($this->attributes->find(self::BUSINESS, 'khong-ton-tai'));
    }

    public function testSaveUpsertsByCode(): void
    {
        $first = $this->attributes->save(self::BUSINESS, ['code' => 'color', 'name' => 'Màu', 'type' => 'select']);
        $second = $this->attributes->save(self::BUSINESS, ['code' => 'color', 'name' => 'Màu sắc', 'type' => 'select']);

        $this->assertSame($first['id'], $second['id'], 'upsert phải giữ nguyên id');
        $this->assertSame('Màu sắc', $this->attributes->find(self::BUSINESS, 'color')['name']);

        $result = $this->attributes->search(self::BUSINESS, ['code' => 'color']);
        $this->assertSame(1, $result['total']);
    }

    public function testSearchFiltersAndAllSorted(): void
    {
        $this->attributes->save(self::BUSINESS, ['code' => 'z-last', 'type' => 'text', 'sort_order' => 10]);
        $this->attributes->save(self::BUSINESS, ['code' => 'a-first', 'type' => 'text', 'sort_order' => 0]);
        $this->attributes->save(self::BUSINESS, ['code' => 'warehouse', 'type' => 'warehouse_link']);
        $this->attributes->save(self::BUSINESS, ['code' => 'disabled', 'type' => 'text', 'status' => 'archived']);

        $byType = $this->attributes->search(self::BUSINESS, ['type' => 'warehouse_link']);
        $this->assertSame(1, $byType['total']);
        $this->assertSame('warehouse', $byType['items'][0]['code']);

        $page = $this->attributes->search(self::BUSINESS, ['status' => 'active'], 0, 2);
        $this->assertSame(3, $page['total']);
        $this->assertCount(2, $page['items']);

        $all = $this->attributes->all(self::BUSINESS);
        $codes = array_column($all, 'code');
        $this->assertSame(['a-first', 'warehouse', 'z-last'], $codes, 'all() sort theo sort_order, code và bỏ status != active');

        $this->assertTrue($this->attributes->delete(self::BUSINESS, 'disabled'));
        $this->assertFalse($this->attributes->delete(self::BUSINESS, 'disabled'));
    }

    public function testDefinitionRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->attributes->save(self::BUSINESS, ['code' => 'bad', 'type' => 'sai-type']);
    }

    public function testValueSaveDerivesWarehouseFromWarehouseLink(): void
    {
        // warehouse_link: không truyền warehouse_code → suy từ value.
        $saved = $this->values->save(self::BUSINESS, [
            'attribute_code' => 'warehouse',
            'attribute_type' => 'warehouse_link',
            'product_external_id' => 'prod-1',
            'value' => 'WH-2',
        ]);

        $this->assertSame('WH-2', $saved['warehouse_code']);

        $rows = $this->values->findByProduct(self::BUSINESS, 'prod-1');
        $this->assertCount(1, $rows);
        $this->assertSame('WH-2', $rows[0]['warehouse_code']);
    }

    public function testValueUpsertByKeyAndDelete(): void
    {
        $data = [
            'attribute_code' => 'binding_type',
            'product_external_id' => 'prod-1',
            'variant_external_id' => 'var-1',
            'value' => 'hardcover',
        ];
        $first = $this->values->save(self::BUSINESS, $data);
        $update = $data;
        $update['value'] = 'paperback';
        $second = $this->values->save(self::BUSINESS, $update);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('paperback', $second['value']);
        $this->assertSame(1, $this->values->search(self::BUSINESS, ['product_external_id' => 'prod-1'])['total']);

        $this->assertTrue($this->values->delete(self::BUSINESS, (string) $first['id']));
        $this->assertSame(0, $this->values->search(self::BUSINESS, ['product_external_id' => 'prod-1'])['total']);
    }

    public function testValueRequiresAttributeAndProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->values->save(self::BUSINESS, ['attribute_code' => 'color']);
    }

    public function testValueScopeSeparatesVariantAndOption(): void
    {
        $base = ['attribute_code' => 'color', 'product_external_id' => 'prod-1'];
        $this->values->save(self::BUSINESS, $base + ['value' => 'red']);
        $this->values->save(self::BUSINESS, $base + ['variant_external_id' => 'var-1', 'value' => 'blue']);
        $this->values->save(self::BUSINESS, $base + ['option_external_id' => 'opt-1', 'value' => 'green']);

        // Khác warehouse_code → không trùng key upsert.
        $this->assertSame(3, $this->values->search(self::BUSINESS, ['product_external_id' => 'prod-1'])['total']);

        // Row gắn option có warehouse filter '' khớp mọi row có warehouse ''.
        $scoped = $this->values->search(self::BUSINESS, ['warehouse_code' => '']);
        $this->assertSame(3, $scoped['total']);
    }
}
