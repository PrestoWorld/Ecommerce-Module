<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Contracts\PaymentRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class PaymentRepository extends AbstractRepository implements PaymentRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('payments')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('payments')),
            $businessId,
            $filters,
        )->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('payments'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByInvoice(string $businessId, string $invoiceExternalId): array
    {
        $rows = $this->db->select('*')->from($this->table('payments'))
            ->where('business_id', $businessId)
            ->where('invoice_external_id', $invoiceExternalId)
            ->orderBy('created_at', 'DESC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function findByTransactionId(string $businessId, string $transactionId): ?array
    {
        $row = $this->db->select('*')->from($this->table('payments'))
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'pay_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $number = $data['number'] ?? 'PAY-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'invoice_id' => (string) ($data['invoice_id'] ?? $data['invoiceId'] ?? ''),
            'invoice_external_id' => (string) ($data['invoice_external_id'] ?? $data['invoiceExternalId'] ?? ''),
            'number' => $number,
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? 'VND'),
            'method' => (string) ($data['method'] ?? ''),
            'gateway' => (string) ($data['gateway'] ?? ''),
            'status' => (string) ($data['status'] ?? 'pending'),
            'transaction_id' => $data['transaction_id'] ?? $data['transactionId'] ?? null,
            'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $data['gatewayTransactionId'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? $data['gatewayResponse'] ?? null,
            'paid_by' => $data['paid_by'] ?? $data['paidBy'] ?? null,
            'paid_by_id' => $data['paid_by_id'] ?? $data['paidById'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
            'completed_at' => $data['completed_at'] ?? $data['completedAt'] ?? null,
        ];

        $existing = $this->db->select('id')->from($this->table('payments'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('payments'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('payments'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function updateStatus(string $businessId, string $externalId, string $status, array $gatewayData = []): array
    {
        $validStatuses = ['pending', 'processing', 'success', 'failed', 'refunded', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid payment status: $status");
        }

        $values = [
            'status' => $status,
            'updated_at' => time(),
            ...$gatewayData,
        ];

        if ($status === 'success') {
            $values['completed_at'] = time();
        }

        $result = $this->db->update($this->table('payments'), $values, [
            'business_id' => $businessId,
            'external_id' => $externalId,
        ])->run();

        if ($result === 0) {
            throw new \InvalidArgumentException("Payment not found: $externalId");
        }

        return $this->find($businessId, $externalId) ?? [];
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['method']) && is_string($filters['method']) && $filters['method'] !== '') {
            $query->where('method', $filters['method']);
        }

        if (isset($filters['gateway']) && is_string($filters['gateway']) && $filters['gateway'] !== '') {
            $query->where('gateway', $filters['gateway']);
        }

        if (isset($filters['invoice_external_id']) && is_string($filters['invoice_external_id']) && $filters['invoice_external_id'] !== '') {
            $query->where('invoice_external_id', $filters['invoice_external_id']);
        }

        if (isset($filters['date_from']) && is_numeric($filters['date_from'])) {
            $query->where('created_at', '>=', (int) $filters['date_from']);
        }

        if (isset($filters['date_to']) && is_numeric($filters['date_to'])) {
            $query->where('created_at', '<=', (int) $filters['date_to']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('number', 'LIKE', '%' . $keyword . '%')
                ->where('external_id', 'LIKE', '%' . $keyword . '%')
                ->where('transaction_id', 'LIKE', '%' . $keyword . '%'));
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'number', 'amount', 'status'], true)) {
                continue;
            }
            $column = $key;
            $direction = strtolower((string) $value) === 'asc' ? 'ASC' : 'DESC';
            break;
        }

        return [$column, $direction];
    }

    private function decodeJson(?string $value): array
    {
        if (!$value) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}