<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Domain\Billing\Storage;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\Ecommerce\Domain\Billing\Contracts\InvoiceRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\SharedKernel\Storage\AbstractRepository;

final class InvoiceRepository extends AbstractRepository implements InvoiceRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);

        $query = $this->applyFilters(
            $this->db->select('*')->from($this->table('invoices')),
            $businessId,
            $filters,
        );

        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();

        $total = $this->applyFilters(
            $this->db->select('*')->from($this->table('invoices')),
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
        $row = $this->db->select('*')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($row === false) {
            return null;
        }

        $row = $this->decoded($row);
        $row['items'] = $this->decodeJson($row['items'] ?? '[]');
        $row['payments'] = $this->decodeJson($row['payments'] ?? '[]');
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? '{}');

        return $row;
    }

    public function findByNumber(string $businessId, string $number): ?array
    {
        $row = $this->db->select('*')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('number', $number)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findByOrder(string $businessId, string $orderExternalId): array
    {
        $rows = $this->db->select('*')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('order_external_id', $orderExternalId)
            ->orderBy('created_at', 'DESC')
            ->run()->fetchAll();

        return $this->rows($rows);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'inv_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $number = $data['number'] ?? 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $items = $data['items'] ?? [];
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

        $payments = $data['payments'] ?? [];
        $paymentsJson = json_encode($payments, JSON_UNESCAPED_UNICODE);

        $subtotal = $data['subtotal'] ?? array_sum(array_map(fn ($i) => $i['total_price'] ?? 0, $items));
        $taxTotal = $data['tax_total'] ?? array_sum(array_map(fn ($i) => $i['tax_amount'] ?? 0, $items));
        $discountTotal = $data['discount_total'] ?? array_sum(array_map(fn ($i) => $i['discount_amount'] ?? 0, $items));
        $total = $subtotal + $taxTotal - $discountTotal;
        $paidAmount = $data['paid_amount'] ?? array_sum(array_map(fn ($p) => $p['amount'] ?? 0, $payments));
        $balanceDue = max(0, $total - $paidAmount);

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'number' => $number,
            'order_id' => (string) ($data['order_id'] ?? $data['orderId'] ?? ''),
            'order_external_id' => (string) ($data['order_external_id'] ?? $data['orderExternalId'] ?? ''),
            'customer_id' => (string) ($data['customer_id'] ?? $data['customerId'] ?? ''),
            'customer_external_id' => (string) ($data['customer_external_id'] ?? $data['customerExternalId'] ?? ''),
            'type' => (string) ($data['type'] ?? 'sale'),
            'status' => (string) ($data['status'] ?? 'draft'),
            'items' => $itemsJson,
            'payments' => $paymentsJson,
            'subtotal' => (int) $subtotal,
            'tax_total' => (int) $taxTotal,
            'discount_total' => (int) $discountTotal,
            'total' => (int) $total,
            'paid_amount' => (int) $paidAmount,
            'balance_due' => (int) $balanceDue,
            'currency' => (string) ($data['currency'] ?? 'VND'),
            'issue_date' => (int) ($data['issue_date'] ?? $data['issueDate'] ?? $now),
            'due_date' => (int) ($data['due_date'] ?? $data['dueDate'] ?? ($now + 86400 * 30)),
            'paid_at' => $data['paid_at'] ?? $data['paidAt'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $data['createdAt'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $data['updatedAt'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('invoices'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $existing = (array) $existing;
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('invoices'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['items'] = $this->decodeJson($values['items']);
        $values['payments'] = $this->decodeJson($values['payments']);
        $values['metadata'] = $this->decodeJson($values['metadata']);

        return $values;
    }

    public function updateStatus(string $businessId, string $externalId, string $status): array
    {
        $validStatuses = ['draft', 'issued', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid invoice status: $status");
        }

        $values = [
            'status' => $status,
            'updated_at' => time(),
        ];

        if ($status === 'paid') {
            $values['paid_at'] = time();
        }

        $result = $this->db->update($this->table('invoices'), $values, [
            'business_id' => $businessId,
            'external_id' => $externalId,
        ])->run();

        if ($result === 0) {
            throw new \InvalidArgumentException("Invoice not found: $externalId");
        }

        return $this->find($businessId, $externalId) ?? [];
    }

    public function markAsPaid(string $businessId, string $externalId, string $paymentId): array
    {
        $invoice = $this->find($businessId, $externalId);
        if (!$invoice) {
            throw new \InvalidArgumentException("Invoice not found: $externalId");
        }

        $payment = null;
        foreach ($invoice['payments'] ?? [] as $p) {
            if ($p['external_id'] === $paymentId || $p['id'] === $paymentId) {
                $payment = $p;
                break;
            }
        }

        if (!$payment) {
            throw new \InvalidArgumentException("Payment not found: $paymentId");
        }

        $newPaidAmount = $invoice['paid_amount'] + $payment['amount'];
        $newBalanceDue = max(0, $invoice['total'] - $newPaidAmount);
        $newStatus = $newBalanceDue === 0 ? 'paid' : 'partially_paid';

        $values = [
            'paid_amount' => $newPaidAmount,
            'balance_due' => $newBalanceDue,
            'status' => $newStatus,
            'paid_at' => $newStatus === 'paid' ? time() : $invoice['paid_at'],
            'updated_at' => time(),
        ];

        $this->db->update($this->table('invoices'), $values, ['id' => $invoice['id']])->run();

        return $this->find($businessId, $externalId) ?? [];
    }

    public function getCustomerInvoices(string $businessId, string $customerExternalId, int $offset = 0, int $size = 20): array
    {
        $query = $this->db->select('*')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('customer_external_id', $customerExternalId)
            ->orderBy('created_at', 'DESC')
            ->offset($offset)
            ->limit($size);

        $items = $query->run()->fetchAll();
        $total = (clone $query)->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    public function getOverdueInvoices(string $businessId, int $offset = 0, int $size = 50): array
    {
        $now = time();

        $query = $this->db->select('*')->from($this->table('invoices'))
            ->where('business_id', $businessId)
            ->where('status', 'NOT IN', ['paid', 'cancelled', 'refunded'])
            ->where('due_date', '<', $now)
            ->orderBy('due_date', 'ASC')
            ->offset($offset)
            ->limit($size);

        $items = $query->run()->fetchAll();
        $total = (clone $query)->count();

        return [
            'items' => $this->rows($items),
            'total' => $total,
        ];
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['customer_external_id']) && is_string($filters['customer_external_id']) && $filters['customer_external_id'] !== '') {
            $query->where('customer_external_id', $filters['customer_external_id']);
        }

        if (isset($filters['order_external_id']) && is_string($filters['order_external_id']) && $filters['order_external_id'] !== '') {
            $query->where('order_external_id', $filters['order_external_id']);
        }

        if (isset($filters['date_from']) && is_numeric($filters['date_from'])) {
            $query->where('issue_date', '>=', (int) $filters['date_from']);
        }

        if (isset($filters['date_to']) && is_numeric($filters['date_to'])) {
            $query->where('issue_date', '<=', (int) $filters['date_to']);
        }

        if (isset($filters['keyword']) && is_string($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(fn (SelectQuery $q) => $q
                ->where('number', 'LIKE', '%' . $keyword . '%')
                ->where('external_id', 'LIKE', '%' . $keyword . '%'));
        }

        if (isset($filters['overdue']) && filter_var($filters['overdue'], FILTER_VALIDATE_BOOLEAN)) {
            $now = time();
            $query->where('status', 'NOT IN', ['paid', 'cancelled', 'refunded'])
                ->where('due_date', '<', $now);
        }

        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at';
        $direction = 'DESC';

        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'number', 'total', 'issue_date', 'due_date', 'status'], true)) {
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