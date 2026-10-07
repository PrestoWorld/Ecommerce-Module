<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Driver\SQLite\Schema\SQLiteTable;
use PrestoWorld\Modules\Ecommerce\Tests\TestCase;

abstract class RepositoryTestCase extends TestCase
{
    protected const PREFIX = 'bookowl_';

    protected DatabaseInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new DatabaseConfig([
            'default' => 'default',
            'connections' => [
                'sqlite' => new SQLiteDriverConfig(),
            ],
            'databases' => [
                'default' => ['connection' => 'sqlite'],
            ],
        ]);

        $manager = new DatabaseManager($config);
        $this->db = $manager->database('default');
    }

    protected function schema(string $table): SQLiteTable
    {
        return $this->db->table($table)->getSchema();
    }

    protected function createTable(string $name, callable $columns): void
    {
        $schema = $this->schema($name);
        $schema->primary('id');

        $columns($schema);

        $schema->save();
    }
}