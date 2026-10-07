<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Ship;

use PrestoWorld\Modules\Ecommerce\Contracts\ShipmentRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;

final class ShipmentRepository extends AbstractRepository implements ShipmentRepositoryInterface
{
    public function create(string $businessId, array $data): array
    {
        $orderId = (int) ($data['order_id'] ?? $data['orderId'] ?? 0);
        $now = time();

        $values = [
            'business_id' => $businessId,
            'order_id' => $orderId,
            'app_order_id' => isset($data['app_order_id']) ? (string) $data['app_order_id'] : '',
            'status' => (string) ($data['status'] ?? ''),
            'status_name' => (string) ($data['status_name'] ?? $data['statusName'] ?? ''),
            'carrier_id' => isset($data['carrier_id']) ? (int) $data['carrier_id'] : 0,
            'carrier_service_id' => isset($data['carrier_service_id']) ? (int) $data['carrier_service_id'] : 0,
            'carrier_code' => (string) ($data['carrier_code'] ?? ''),
            'total_fee' => (int) ($data['total_fee'] ?? $data['totalFee'] ?? 0),
            'ship_fee' => (int) ($data['ship_fee'] ?? $data['shipFee'] ?? 0),
            'customer_ship_fee' => (int) ($data['customer_ship_fee'] ?? $data['customerShipFee'] ?? 0),
            'total_cod' => (int) ($data['total_cod'] ?? $data['totalCod'] ?? 0),
            'send_carrier_error' => isset($data['send_carrier_error'])
                ? (string) $data['send_carrier_error']
                : (isset($data['sendCarrierError']) ? (string) $data['sendCarrierError'] : null),
            'payload' => $this->encode($data['payload'] ?? $data),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('shipments'))
            ->where('business_id', $businessId)
            ->where('order_id', $orderId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('shipments'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = (int) ($existing['id'] ?? 0);
            $this->db->update($this->table('shipments'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode(is_string($values['payload']) ? $values['payload'] : null);

        return $values;
    }

    public function find(string $businessId, int $orderId): ?array
    {
        $row = $this->db->select('*')->from($this->table('shipments'))
            ->where('business_id', $businessId)
            ->where('order_id', $orderId)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = (array) $row;
        $row['payload'] = $this->decode(is_string($row['payload'] ?? null) ? $row['payload'] : null);

        return $row;
    }

    public function update(string $businessId, int $orderId, array $fields): void
    {
        $values = [];
        foreach ($fields as $key => $value) {
            $value = is_array($value) ? $this->encode($value) : $value;
            $values[$this->toCamelToSnake($key)] = $value;
        }
        $values['updated_at'] = time();

        $this->db->update($this->table('shipments'), $values, [
            'business_id' => $businessId,
            'order_id' => $orderId,
        ])->run();
    }

    private function toCamelToSnake(string $key): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
    }
}