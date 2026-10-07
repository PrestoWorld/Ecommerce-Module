<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Common;

use PrestoWorld\Modules\Ecommerce\Contracts\RateLimitRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;
use Throwable;

final class RateLimitRepository extends AbstractRepository implements RateLimitRepositoryInterface
{
    public function hit(string $key, string $url, int $windowStart): int
    {
        $row = $this->db->select('*')->from($this->table('rate_limits'))
            ->where('bucket', $key)
            ->where('url', $url)
            ->run()->fetch();

        if ($row === false) {
            try {
                $this->db->insert($this->table('rate_limits'))->values([
                    'bucket' => $key,
                    'url' => $url,
                    'window_start' => $windowStart,
                    'count' => 1,
                    'locked_until' => 0,
                ])->run();

                return 1;
            } catch (Throwable) {
                return $this->hit($key, $url, $windowStart);
            }
        }

        $count = $this->int($row, 'count') + 1;
        if ($this->int($row, 'window_start') !== $windowStart) {
            $count = 1;
        }

        $this->db->update($this->table('rate_limits'), [
            'window_start' => $windowStart,
            'count' => $count,
        ], ['id' => $this->int($row, 'id')])->run();

        return $count;
    }

    public function lockedUntil(string $key, string $url): int
    {
        $row = $this->db->select('locked_until')->from($this->table('rate_limits'))
            ->where('bucket', $key)
            ->where('url', $url)
            ->run()->fetch();

        return $row === false ? 0 : $this->int($row, 'locked_until');
    }

    public function lock(string $key, string $url, int $lockedUntil): void
    {
        $this->db->update($this->table('rate_limits'), [
            'locked_until' => $lockedUntil,
        ], [
            'bucket' => $key,
            'url' => $url,
        ])->run();
    }
}