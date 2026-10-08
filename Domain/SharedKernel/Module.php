<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\SharedKernel;

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
            $metadata = ['name' => 'shared-kernel'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        // Shared kernel provides only models, events, exceptions - no storage/handlers
    }

    public function boot(): void
    {
    }

    public function getName(): string
    {
        return 'SharedKernel';
    }
}