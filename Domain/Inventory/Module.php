<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\LocationRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockMovementRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockRepositoryInterface;
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
}