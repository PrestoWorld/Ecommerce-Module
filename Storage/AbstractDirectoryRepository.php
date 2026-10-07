<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage;

abstract class AbstractDirectoryRepository extends AbstractRepository
{
    abstract protected function branch(): string;

    public function locations(string $version, string $type, ?int $parentId = null): array
    {
        $query = $this->db->select('*')->from($this->table('locations'))
            ->where('version', $version)
            ->where('type', $type);

        if ($parentId === null) {
            $query->where('parent_id', null);
        } else {
            $query->where('parent_id', $parentId);
        }

        $rows = $query->orderBy('name', 'ASC')->run()->fetchAll();

        return array_map(function (array $row) {
            $location = [
                'id' => (string) $row['location_id'],
                'name' => (string) $row['name'],
            ];

            if (isset($row['other_name'])) {
                $location['otherName'] = (string) $row['other_name'];
            }
            if (isset($row['parent_id'])) {
                $location['parentId'] = (int) $row['parent_id'];
            }

            return $location;
        }, $rows);
    }

    public function saveLocations(array $rows): void
    {
        foreach ($rows as $row) {
            $locationId = (string) ($row['id'] ?? '');
            if ($locationId === '') {
                continue;
            }

            $values = [
                'version' => $this->branch(),
                'type' => (string) ($row['type'] ?? ''),
                'location_id' => $locationId,
                'parent_id' => isset($row['parentId']) ? (int) $row['parentId'] : null,
                'name' => (string) ($row['name'] ?? ''),
                'other_name' => isset($row['otherName']) ? (string) $row['otherName'] : null,
            ];

            $existing = $this->db->select('id')->from($this->table('locations'))
                ->where('version', $values['version'])
                ->where('type', $values['type'])
                ->where('location_id', $locationId)
                ->run()->fetch();

            if ($existing === false) {
                $this->db->insert($this->table('locations'))->values($values)->run();
            } else {
                $existing = (array) $existing;
                $this->db->update($this->table('locations'), $values, ['id' => $this->int($existing, 'id')])->run();
            }
        }
    }

    public function carriers(): array
    {
        $rows = $this->db->select('*')->from($this->table('carriers'))
            ->where('branch', $this->branch())
            ->orderBy('carrier_id', 'ASC')
            ->run()->fetchAll();

        return array_map(function (array $row) {
            $carrier = [
                'id' => (int) $row['carrier_id'],
                'name' => (string) $row['name'],
            ];

            if (isset($row['logo'])) {
                $carrier['logo'] = (string) $row['logo'];
            }
            if (isset($row['status'])) {
                $carrier['status'] = (int) $row['status'];
            }
            if (isset($row['short_name'])) {
                $carrier['shortName'] = (string) $row['short_name'];
            }
            if (isset($row['services'])) {
                $services = $this->decode((string) $row['services']);
                if ($services !== []) {
                    $carrier['services'] = $services;
                }
            }

            return $carrier;
        }, $rows);
    }

    public function saveCarriers(array $rows): void
    {
        foreach ($rows as $row) {
            $carrierId = (int) ($row['id'] ?? 0);
            if ($carrierId <= 0) {
                continue;
            }

            $values = [
                'branch' => $this->branch(),
                'carrier_id' => $carrierId,
                'name' => (string) ($row['name'] ?? ''),
                'logo' => isset($row['logo']) ? (string) $row['logo'] : null,
                'status' => isset($row['status']) ? (int) $row['status'] : 1,
                'short_name' => isset($row['shortName']) ? (string) $row['shortName'] : null,
                'services' => isset($row['services']) ? $this->encode($row['services']) : null,
                'updated_at' => time(),
            ];

            $existing = $this->db->select('id')->from($this->table('carriers'))
                ->where('branch', $this->branch())
                ->where('carrier_id', $carrierId)
                ->run()->fetch();

            if ($existing === false) {
                $this->db->insert($this->table('carriers'))->values($values)->run();
            } else {
                $existing = (array) $existing;
                $this->db->update($this->table('carriers'), $values, ['id' => $this->int($existing, 'id')])->run();
            }
        }
    }
}