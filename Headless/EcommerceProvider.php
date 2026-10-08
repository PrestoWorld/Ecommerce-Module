<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Headless;

use PrestoWorld\Modules\Ecommerce\Contracts\CustomerRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\OrderRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\ProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Headless\Handler\CustomerResourceHandler;
use PrestoWorld\Modules\Ecommerce\Headless\Handler\OrderResourceHandler;
use PrestoWorld\Modules\Ecommerce\Headless\Handler\ProductResourceHandler;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;
use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;

/**
 * Exposes the Ecommerce POS data through the Headless-CMS gateway under the
 * "ecommerce" namespace:
 *
 *   GET /api/headless/ecommerce/products
 *   GET /api/headless/ecommerce/orders/{external_id}
 *   GET /api/headless/ecommerce/customers
 *
 * Reads are public; writes require an API key carrying the matching scope.
 * The provider is only instantiated when a request targets this namespace.
 */
final class EcommerceProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private OrderRepositoryInterface $orders,
        private CustomerRepositoryInterface $customers,
        private string $businessId,
    ) {
    }

    public function namespace(): string
    {
        return 'ecommerce';
    }

    public function resources(): array
    {
        return [
            'products' => new ResourceDefinition(
                name: 'products',
                handler: new ProductResourceHandler($this->products, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update'],
                publicRead: true,
            ),
            'orders' => new ResourceDefinition(
                name: 'orders',
                handler: new OrderResourceHandler($this->orders, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update'],
                publicRead: true,
            ),
            'customers' => new ResourceDefinition(
                name: 'customers',
                handler: new CustomerResourceHandler($this->customers, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update'],
                publicRead: true,
            ),
        ];
    }
}