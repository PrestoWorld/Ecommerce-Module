<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Headless;

use PrestoWorld\Modules\Ecommerce\Headless\Handler\ProductResourceHandler;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryProductRepository;
use PrestoWorld\Modules\Ecommerce\Tests\TestCase;
use PrestoWorld\Modules\HeadlessCMS\Exceptions\HeadlessException;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

final class ProductResourceHandlerTest extends TestCase
{
    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = new InMemoryProductRepository();
        $this->products->items = [
            'p1' => ['external_id' => 'p1', 'name' => 'Áo thun', 'price' => 100],
            'p2' => ['external_id' => 'p2', 'name' => 'Quần jean', 'price' => 200],
        ];
    }

    public function test_list_returns_unwrapped_payload_and_total(): void
    {
        $result = $this->handler()->list(Query::fromArray([], 20, 100));

        self::assertSame(2, $result['total']);
        self::assertSame('Áo thun', $result['items'][0]['name']);
        self::assertArrayNotHasKey('business_id', $result['items'][0]);
    }

    public function test_list_respects_pagination_and_search(): void
    {
        $result = $this->handler()->list(Query::fromArray(['search' => 'jean'], 20, 100));

        self::assertSame(1, $result['total']);
        self::assertSame('p2', $result['items'][0]['external_id']);
    }

    public function test_read_returns_item_or_null(): void
    {
        $handler = $this->handler();

        self::assertSame('Áo thun', $handler->read('p1')['name'] ?? null);
        self::assertNull($handler->read('missing'));
    }

    public function test_create_requires_external_id(): void
    {
        $this->expectException(HeadlessException::class);

        $this->handler()->create(['name' => 'Không id']);
    }

    public function test_create_persists_item(): void
    {
        $created = $this->handler()->create(['external_id' => 'p3', 'name' => 'Mũ', 'price' => 50]);

        self::assertSame('Mũ', $created['name']);
        self::assertArrayHasKey('p3', $this->products->items);
    }

    public function test_update_returns_null_when_missing(): void
    {
        self::assertNull($this->handler()->update('missing', ['name' => 'X']));
    }

    public function test_update_merges_into_existing_item(): void
    {
        $updated = $this->handler()->update('p1', ['external_id' => 'p1', 'name' => 'Áo thun mới']);

        self::assertSame('Áo thun mới', $updated['name'] ?? null);
    }

    private function handler(): ProductResourceHandler
    {
        return new ProductResourceHandler($this->products, 'biz-1');
    }
}