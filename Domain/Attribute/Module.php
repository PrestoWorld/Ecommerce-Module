<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Attribute;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeValueRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage\ProductAttributeRepository;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Storage\ProductAttributeValueRepository;
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
            $metadata = ['name' => 'attributes'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('ecommerce.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            ProductAttributeRepositoryInterface::class => ProductAttributeRepository::class,
            ProductAttributeValueRepositoryInterface::class => ProductAttributeValueRepository::class,
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
        return 'Attribute';
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
