<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Common\AuthRepository;

class AuthRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'applications', static function ($table): void {
            $table->string('app_id', 64);
            $table->string('secret_key', 128);
            $table->string('name', 128);
            $table->integer('status')->defaultValue(1);
            $table->bigInteger('created_at');
            $table->index(['app_id']);
        });

        $this->createTable(self::PREFIX . 'access_tokens', static function ($table): void {
            $table->string('app_id', 64);
            $table->string('business_id', 64);
            $table->string('token', 128);
            $table->bigInteger('expires_at');
            $table->bigInteger('created_at');
            $table->bigInteger('last_used_at')->nullable();
            $table->index(['app_id', 'business_id', 'token']);
        });
    }

    private function repo(): AuthRepository
    {
        return new AuthRepository($this->db, self::PREFIX);
    }

    public function test_save_and_find_application(): void
    {
        $repo = $this->repo();
        $repo->saveApplication('app1', 'secret-a', 'Oreka');

        $found = $repo->findApplication('app1');

        $this->assertSame('app1', $found['app_id']);
        $this->assertSame('secret-a', $found['secret_key']);
        $this->assertNull($repo->findApplication('ghost'));
    }

    public function test_save_application_upserts(): void
    {
        $repo = $this->repo();
        $repo->saveApplication('app1', 'secret-a', 'Oreka');
        $repo->saveApplication('app1', 'secret-b', 'Oreka 2');

        $found = $repo->findApplication('app1');

        $this->assertSame('secret-b', $found['secret_key']);
        $this->assertSame('Oreka 2', $found['name']);
    }

    public function test_token_flow(): void
    {
        $repo = $this->repo();
        $repo->saveToken('app1', 'biz1', 'tok-1', 2000000000);

        $found = $repo->findToken('app1', 'biz1', 'tok-1');

        $this->assertSame('tok-1', $found['token']);
        $this->assertSame(2000000000, $found['expires_at']);
        $this->assertNull($repo->findToken('app1', 'biz1', 'other'));
        $this->assertNull($repo->findToken('app2', 'biz1', 'tok-1'));
    }

    public function test_save_token_replaces_existing_for_business(): void
    {
        $repo = $this->repo();
        $repo->saveToken('app1', 'biz1', 'old-token', 100);
        $repo->saveToken('app1', 'biz1', 'new-token', 200);

        $this->assertNull($repo->findToken('app1', 'biz1', 'old-token'));
        $this->assertSame('new-token', $repo->findToken('app1', 'biz1', 'new-token')['token']);
    }

    public function test_touch_token(): void
    {
        $repo = $this->repo();
        $repo->saveToken('app1', 'biz1', 'tok-1', 2000000000);
        $repo->touchToken('app1', 'biz1', 123);

        $found = $repo->findToken('app1', 'biz1', 'tok-1');

        $this->assertSame(123, $found['last_used_at']);
    }
}