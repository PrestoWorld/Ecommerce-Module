<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\LocationRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockDriverInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockMovementRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\WebhookRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock\EcommerceStockDriver;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock\StockDriverRegistry;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage\LocationRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage\StockMovementRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Storage\StockRepository;
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
            $metadata = ['name' => 'inventory'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->bindRepositories();
        $this->bindStockDriver();
    }

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'Inventory';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('inventory.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            LocationRepositoryInterface::class => LocationRepository::class,
            StockRepositoryInterface::class => StockRepository::class,
            StockMovementRepositoryInterface::class => StockMovementRepository::class,
        ];

        foreach ($bindings as $abstract => $concrete) {
            $this->app->singleton($abstract, fn (Application $app) => new $concrete($db($app), $prefix($app)));
        }
    }

    private function resolveDatabase(Application $app): DatabaseInterface
    {
        $db = $app->make(DatabaseInterface::class);
        if (!$db instanceof DatabaseInterface) {
            throw new \RuntimeException('DatabaseInterface is not bound in the container.');
        }

        return $db;
    }

    /**
     * Bind the stock strategy registry and the active driver.
     *
     * Default driver ships here (pw_stock_items + pw_stock_movements).
     * Optional modules may register richer drivers (e.g. "inventory");
     * the resolver prefers them when configured as "auto".
     */
    private function bindStockDriver(): void
    {
        if (!$this->app->has(StockDriverRegistry::class)) {
            $this->app->singleton(StockDriverRegistry::class, fn (): StockDriverRegistry => new StockDriverRegistry());
        }

        $registry = $this->app->make(StockDriverRegistry::class);
        if (!$registry instanceof StockDriverRegistry) {
            throw new \RuntimeException('StockDriverRegistry is not bound in the container.');
        }

        if (!$registry->has('ecommerce')) {
            $registry->register('ecommerce', fn (Application $app): StockDriverInterface => new EcommerceStockDriver(
                $this->resolveDatabase($app),
                (string) ($app->config('inventory.table_prefix', 'pw_') ?: 'pw_'),
                $this->stockBusinessId($app),
                $app->make(StockRepositoryInterface::class),
                $app->make(StockMovementRepositoryInterface::class),
                $app->make(WebhookRepositoryInterface::class),
            ));
        }

        $this->app->singleton(StockDriverInterface::class, function (Application $app): StockDriverInterface {
            $registry = $app->make(StockDriverRegistry::class);
            if (!$registry instanceof StockDriverRegistry) {
                throw new \RuntimeException('StockDriverRegistry is not bound in the container.');
            }

            $configured = (string) ($app->config('ecommerce.stock.driver', 'auto') ?: 'auto');
            $name = $configured === 'auto'
                ? ($registry->has('inventory') ? 'inventory' : 'ecommerce')
                : $configured;

            if (!$registry->has($name)) {
                $name = 'ecommerce';
            }

            return $registry->make($name, $app);
        });
    }

    private function stockBusinessId(Application $app): string
    {
        $value = $app->config('catalog.headless.business_id', '');
        if (!is_string($value) || $value === '') {
            $value = $app->config('ecommerce.headless.business_id', '');
        }

        return is_string($value) ? $value : '';
    }
}