<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Common;

use PrestoWorld\Modules\Ecommerce\Contracts\SyncCursorRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;

final class SyncCursorRepository extends AbstractRepository implements SyncCursorRepositoryInterface
{
    public function get(string $resource): ?array
    {
        $row = $this->db->select('cursor')->from($this->table('sync_cursors'))
            ->where('resource', $resource)
            ->run()->fetch();

        if (!is_array($row) || !isset($row['cursor'])) {
            return null;
        }

        $cursor = $this->decode(is_string($row['cursor']) ? $row['cursor'] : null);

        return $cursor === [] ? null : $cursor;
    }

    public function set(string $resource, array $cursor): void
    {
        $existing = $this->db->select('id')->from($this->table('sync_cursors'))
            ->where('resource', $resource)
            ->run()->fetch();

        $values = [
            'resource' => $resource,
            'cursor' => $this->encode($cursor),
            'updated_at' => time(),
        ];

        if ($existing === false) {
            $this->db->insert($this->table('sync_cursors'))->values($values)->run();

            return;
        }

        $existing = (array) $existing;
        unset($values['resource']);
        $this->db->update($this->table('sync_cursors'), $values, ['id' => (int) ($existing['id'] ?? 0)])->run();
    }
}