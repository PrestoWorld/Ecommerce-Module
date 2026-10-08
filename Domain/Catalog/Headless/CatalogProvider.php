<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless;

use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler\ChannelProductResourceHandler;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler\ProductMasterResourceHandler;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler\ProductVariantOptionResourceHandler;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\Handler\ProductVariantResourceHandler;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;
use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;

/**
 * Exposes the Catalog data through the Headless-CMS gateway under the
 * "catalog" namespace:
 *
 *   GET /api/headless/catalog/product_masters
 *   GET /api/headless/catalog/product_variants
 *   GET /api/headless/catalog/channel_products
 *
 * Reads are public; writes require an API key carrying the matching scope.
 * The provider is only instantiated when a request targets this namespace.
 */
final class CatalogProvider implements ProviderInterface
{
    public function __construct(
        private ProductMasterRepositoryInterface $productMasters,
        private ProductVariantRepositoryInterface $productVariants,
        private ProductVariantOptionRepositoryInterface $productVariantOptions,
        private ChannelProductRepositoryInterface $channelProducts,
        private string $businessId,
    ) {
    }

    public function namespace(): string
    {
        return 'catalog';
    }

    public function resources(): array
    {
        return [
            'product_masters' => new ResourceDefinition(
                name: 'product_masters',
                handler: new ProductMasterResourceHandler($this->productMasters, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
            'product_variants' => new ResourceDefinition(
                name: 'product_variants',
                handler: new ProductVariantResourceHandler($this->productVariants, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
            'product_variant_options' => new ResourceDefinition(
                name: 'product_variant_options',
                handler: new ProductVariantOptionResourceHandler($this->productVariantOptions, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
            'channel_products' => new ResourceDefinition(
                name: 'channel_products',
                handler: new ChannelProductResourceHandler($this->channelProducts, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
        ];
    }
}