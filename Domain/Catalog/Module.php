<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Catalog;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ChannelProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductMasterRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantOptionRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Contracts\ProductVariantRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Headless\CatalogProvider;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ChannelProductRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductMasterRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductVariantOptionRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Storage\Catalog\ProductVariantRepository;
use Witals\Framework\Application;
use Witals\Framework\Module\Module as WitalsModule;

class Module extends WitalsModule
{
    public function __construct(
        protected Application $app,
        protected string $path = '',
        protected array $metadata = [],
    ) {
        if ($path === '') {
            $path = __DIR__;
        }
        if ($metadata === []) {
            $metadata = ['name' => 'catalog'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->bindRepositories();

        $this->app->singleton(CatalogProvider::class, function (Application $app): CatalogProvider {
            return new CatalogProvider(
                $this->resolve($app, ProductMasterRepositoryInterface::class),
                $this->resolve($app, ProductVariantRepositoryInterface::class),
                $this->resolve($app, ProductVariantOptionRepositoryInterface::class),
                $this->resolve($app, ChannelProductRepositoryInterface::class),
                $this->headlessBusinessId($app),
            );
        });
    }

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'Catalog';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('catalog.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            ProductMasterRepositoryInterface::class => ProductMasterRepository::class,
            ProductVariantRepositoryInterface::class => ProductVariantRepository::class,
            ProductVariantOptionRepositoryInterface::class => ProductVariantOptionRepository::class,
            ChannelProductRepositoryInterface::class => ChannelProductRepository::class,
        ];

        foreach ($bindings as $abstract => $concrete) {
            $this->app->singleton($abstract, fn (Application $app) => new $concrete($db($app), $prefix($app)));
        }
    }

    private function headlessBusinessId(Application $app): string
    {
        return $this->configString(
            $app,
            'catalog.headless.business_id',
            '',
        );
    }

    private function configString(Application $app, string $key, string $default): string
    {
        $value = $app->config($key, $default);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $abstract
     *
     * @return T
     */
    private function resolve(Application $app, string $abstract): object
    {
        $instance = $app->make($abstract);
        if (!$instance instanceof $abstract) {
            throw new \RuntimeException(sprintf('%s is not bound in the container.', $abstract));
        }

        return $instance;
    }

    private function resolveDatabase(Application $app): DatabaseInterface
    {
        $db = $app->make(DatabaseInterface::class);
        if (!$db instanceof DatabaseInterface) {
            throw new \RuntimeException('DatabaseInterface is not bound in the container.');
        }

        return $db;
    }
}