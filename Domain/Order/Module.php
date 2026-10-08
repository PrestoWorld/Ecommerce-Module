<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Order;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\CartRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\OrderRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Contracts\QuoteRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Storage\OrderRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Storage\CartRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Storage\QuoteRepository;
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
            $metadata = ['name' => 'order'];
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
        return 'Order';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('order.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            OrderRepositoryInterface::class => OrderRepository::class,
            CartRepositoryInterface::class => CartRepository::class,
            QuoteRepositoryInterface::class => QuoteRepository::class,
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