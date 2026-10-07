<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\AffiliateRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\AuthRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\CustomerRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\OrderRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\ProductRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\RateLimitRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\ShipDirectoryRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\ShipmentRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\ShippingDirectoryRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\SyncCursorRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\VpageRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Contracts\WebhookRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Services\NhanhClient;
use PrestoWorld\Modules\Ecommerce\Storage\Affiliate\AffiliateRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Common\AuthRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Common\RateLimitRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Common\SyncCursorRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Common\WebhookRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Pos\CustomerRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Pos\OrderRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Pos\ProductRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Pos\ShippingDirectoryRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Ship\ShipmentRepository;
use PrestoWorld\Modules\Ecommerce\Storage\Vpage\VpageRepository;
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
            $metadata = ['name' => 'ecommerce'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->bindRepositories();

        $this->app->singleton(NhanhClient::class, fn (Application $app) => new NhanhClient($app));
    }

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'Ecommerce';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('ecommerce.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            AuthRepositoryInterface::class => AuthRepository::class,
            RateLimitRepositoryInterface::class => RateLimitRepository::class,
            SyncCursorRepositoryInterface::class => SyncCursorRepository::class,
            WebhookRepositoryInterface::class => WebhookRepository::class,
            OrderRepositoryInterface::class => OrderRepository::class,
            ProductRepositoryInterface::class => ProductRepository::class,
            CustomerRepositoryInterface::class => CustomerRepository::class,
            ShippingDirectoryRepositoryInterface::class => ShippingDirectoryRepository::class,
            ShipmentRepositoryInterface::class => ShipmentRepository::class,
            ShipDirectoryRepositoryInterface::class => \PrestoWorld\Modules\Ecommerce\Storage\Ship\ShippingDirectoryRepository::class,
            VpageRepositoryInterface::class => VpageRepository::class,
            AffiliateRepositoryInterface::class => AffiliateRepository::class,
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