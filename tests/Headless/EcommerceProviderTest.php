<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Headless;

use PrestoWorld\Modules\Ecommerce\Headless\EcommerceProvider;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryCustomerRepository;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryOrderRepository;
use PrestoWorld\Modules\Ecommerce\Tests\Headless\Support\InMemoryProductRepository;
use PrestoWorld\Modules\Ecommerce\Tests\TestCase;

final class EcommerceProviderTest extends TestCase
{
    public function test_namespace_is_ecommerce(): void
    {
        self::assertSame('ecommerce', $this->provider()->namespace());
    }

    public function test_exposes_products_orders_and_customers(): void
    {
        $resources = $this->provider()->resources();

        self::assertSame(['products', 'orders', 'customers'], array_keys($resources));
        self::assertSame('external_id', $resources['products']->key);
        self::assertSame(['list', 'read', 'create', 'update'], $resources['products']->operations);
        self::assertTrue($resources['products']->publicRead);
        self::assertSame('ecommerce:products:write', $resources['products']->writeScopeFor('ecommerce'));
    }

    private function provider(): EcommerceProvider
    {
        return new EcommerceProvider(
            new InMemoryProductRepository(),
            new InMemoryOrderRepository(),
            new InMemoryCustomerRepository(),
            'biz-1',
        );
    }
}