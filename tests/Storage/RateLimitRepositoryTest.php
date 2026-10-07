<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Tests\Storage;

use PrestoWorld\Modules\Ecommerce\Storage\Common\RateLimitRepository;

class RateLimitRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable(self::PREFIX . 'rate_limits', static function ($table): void {
            $table->string('bucket', 128);
            $table->string('url', 128);
            $table->bigInteger('window_start');
            $table->bigInteger('count')->defaultValue(0);
            $table->bigInteger('locked_until')->defaultValue(0);
            $table->index(['bucket', 'url']);
        });
    }

    private function repo(): RateLimitRepository
    {
        return new RateLimitRepository($this->db, self::PREFIX);
    }

    public function test_hit_starts_at_one(): void
    {
        $this->assertSame(1, $this->repo()->hit('k', '/order/list', 100));
    }

    public function test_hit_increments_within_window(): void
    {
        $repo = $this->repo();
        $repo->hit('k', '/order/list', 100);

        $this->assertSame(2, $repo->hit('k', '/order/list', 100));
        $this->assertSame(3, $repo->hit('k', '/order/list', 100));
    }

    public function test_hit_resets_on_new_window(): void
    {
        $repo = $this->repo();
        $repo->hit('k', '/order/list', 100);
        $repo->hit('k', '/order/list', 100);

        $this->assertSame(1, $repo->hit('k', '/order/list', 130));
    }

    public function test_lock_and_locked_until(): void
    {
        $repo = $this->repo();
        $repo->hit('k', '/order/list', 100);
        $this->assertSame(0, $repo->lockedUntil('k', '/order/list'));

        $repo->lock('k', '/order/list', 500);

        $this->assertSame(500, $repo->lockedUntil('k', '/order/list'));
        $this->assertSame(0, $repo->lockedUntil('k', '/other'));
    }
}