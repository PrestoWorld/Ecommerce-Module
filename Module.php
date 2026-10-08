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

// Domain modules
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Module as SharedKernelModule;
use PrestoWorld\Modules\Ecommerce\Domain\Catalog\Module as CatalogModule;
use PrestoWorld\Modules\Ecommerce\Domain\Order\Module as OrderModule;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Module as BillingModule;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Module as TaxModule;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Module as InventoryModule;
use PrestoWorld\Modules\Ecommerce\Domain\Payment\Module as PaymentModule;
use PrestoWorld\Modules\Ecommerce\Domain\Shipping\Module as ShippingModule;
use PrestoWorld\Modules\Ecommerce\Domain\Customer\Module as CustomerModule;
use PrestoWorld\Modules\Ecommerce\Domain\Sales\Module as SalesModule;

// Legacy POS integrations
use PrestoWorld\Modules\Ecommerce\Headless\EcommerceProvider;
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
        // Register domain modules
        $this->registerDomainModules();

        // Bind legacy POS repositories
        $this->bindLegacyRepositories();

        // Nhanh.vn integration
        $this->app->singleton(NhanhClient::class, fn (Application $app) => new NhanhClient($app));

        // Headless CMS provider
        $this->app->singleton(EcommerceProvider::class, function (Application $app): EcommerceProvider {
            return new EcommerceProvider(
                $this->resolve($app, ProductRepositoryInterface::class),
                $this->resolve($app, OrderRepositoryInterface::class),
                $this->resolve($app, CustomerRepositoryInterface::class),
                $this->headlessBusinessId($app),
            );
        });
    }

    private function registerDomainModules(): void
    {
        $domainModules = [
            SharedKernelModule::class,
            CatalogModule::class,
            OrderModule::class,
            BillingModule::class,
            TaxModule::class,
            InventoryModule::class,
            PaymentModule::class,
            ShippingModule::class,
            CustomerModule::class,
            SalesModule::class,
        ];

        foreach ($domainModules as $moduleClass) {
            $module = new $moduleClass($this->app, $this->path . '/Domain/' . (new \ReflectionClass($moduleClass))->getShortName());
            $module->register();
        }
    }

    private function bindLegacyRepositories(): void
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

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'Ecommerce';
    }

    private function headlessBusinessId(Application $app): string
    {
        return $this->configString(
            $app,
            'ecommerce.headless.business_id',
            $this->configString($app, 'nhanh-sync.business_id', ''),
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