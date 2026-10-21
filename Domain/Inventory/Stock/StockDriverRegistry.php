<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock;

use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockDriverInterface;
use Witals\Framework\Application;

/**
 * Registry of named stock driver factories (Service Locator for the
 * Strategy pattern). Modules register their drivers during boot;
 * the resolver picks one by configuration or "auto" preference.
 */
final class StockDriverRegistry
{
    /** @var array<string, callable(Application): StockDriverInterface> */
    private array $factories = [];

    /** @param callable(Application): StockDriverInterface $factory */
    public function register(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->factories);
    }

    public function make(string $name, Application $app): StockDriverInterface
    {
        if (!isset($this->factories[$name])) {
            throw new \InvalidArgumentException("Unknown stock driver: {$name}. Registered: " . implode(', ', $this->names()));
        }

        $driver = ($this->factories[$name])($app);
        if (!$driver instanceof StockDriverInterface) {
            throw new \RuntimeException("Stock driver factory for \"{$name}\" did not return a StockDriverInterface.");
        }

        return $driver;
    }
}
