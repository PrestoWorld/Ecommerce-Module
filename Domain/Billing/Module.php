<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Contracts\InvoiceRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Contracts\PaymentRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Storage\InvoiceRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Storage\PaymentRepository;
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
            $metadata = ['name' => 'billing'];
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
        return 'Billing';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('billing.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            InvoiceRepositoryInterface::class => InvoiceRepository::class,
            PaymentRepositoryInterface::class => PaymentRepository::class,
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