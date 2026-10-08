<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Tax;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Contracts\TaxCalculatorInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Contracts\TaxRateRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Services\TaxCalculator;
use PrestoWorld\Modules\Ecommerce\Domain\Tax\Storage\TaxRateRepository;
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
            $metadata = ['name' => 'tax'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->bindRepositories();

        $this->app->singleton(TaxCalculatorInterface::class, function (Application $app) {
            return new TaxCalculator($app->config('tax.default_business_id', ''));
        });
    }

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'Tax';
    }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('tax.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            TaxRateRepositoryInterface::class => TaxRateRepository::class,
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