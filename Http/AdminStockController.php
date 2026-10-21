<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Http;

use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Contracts\StockDriverInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Inventory\Stock\StockDriverRegistry;
use Witals\Framework\Application;
use Witals\Framework\Http\Request;
use Witals\Framework\Http\Response;

/**
 * Admin REST API for the stock management screen.
 *
 * Routes (registered by Ecommerce\Module::boot):
 *   GET  /api/admin/stock              list stock items (search/status/pagination)
 *   GET  /api/admin/stock/driver       active driver + available drivers
 *   GET  /api/admin/stock/movements    journal entries (movements)
 *   POST /api/admin/stock/adjust       manual adjustment (add|subtract|set)
 */
final class AdminStockController
{
    private const PREFIX = '/api/admin/stock';

    public function __construct(
        protected Application $app,
        protected StockDriverInterface $driver,
        protected StockDriverRegistry $registry,
    ) {
    }

    public function handle(Request $request, ?string $id = null): Response
    {
        $method = strtoupper($request->method());
        $path = rtrim($request->path(), '/');

        try {
            if ($path === self::PREFIX) {
                return $method === 'GET'
                    ? $this->listItems($request)
                    : $this->error('Method not allowed', 405);
            }

            if ($path === self::PREFIX . '/driver') {
                return $method === 'GET'
                    ? $this->driverInfo()
                    : $this->error('Method not allowed', 405);
            }

            if ($path === self::PREFIX . '/movements') {
                return $method === 'GET'
                    ? $this->movements($request)
                    : $this->error('Method not allowed', 405);
            }

            if ($path === self::PREFIX . '/adjust') {
                return $method === 'POST'
                    ? $this->adjust($request)
                    : $this->error('Method not allowed', 405);
            }

            return $this->error('Not found', 404);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Internal error: ' . $e->getMessage(), 500);
        }
    }

    private function listItems(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(200, $perPage));

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'sort' => (string) $request->query('sort', 'title'),
            'order' => (string) $request->query('order', 'asc'),
        ];

        $result = $this->driver->listItems($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data' => $result + ['driver' => $this->driver->name()],
        ]);
    }

    private function driverInfo(): Response
    {
        return Response::json([
            'success' => true,
            'data' => [
                'driver' => $this->driver->name(),
                'available' => $this->registry->names(),
                'configured' => (string) ($this->app->config('ecommerce.stock.driver', 'auto') ?: 'auto'),
            ],
        ]);
    }

    private function movements(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(200, $perPage));

        $filters = [
            'variantExternalId' => trim((string) $request->query('variant', '')),
            'type' => trim((string) $request->query('type', '')),
        ];

        $result = $this->driver->movements($filters, $page, $perPage);

        return Response::json([
            'success' => true,
            'data' => $result + ['driver' => $this->driver->name()],
        ]);
    }

    private function adjust(Request $request): Response
    {
        $input = $request->getJson() ?? [];

        $variantExternalId = trim((string) ($input['variantExternalId'] ?? ''));
        if ($variantExternalId === '') {
            return $this->error('variantExternalId is required', 422);
        }

        $operation = strtolower(trim((string) ($input['operation'] ?? 'set')));
        if (!in_array($operation, ['add', 'subtract', 'set'], true)) {
            return $this->error('operation must be one of: add, subtract, set', 422);
        }

        if (!isset($input['quantity']) || !is_numeric($input['quantity'])) {
            return $this->error('quantity is required and must be numeric', 422);
        }

        $quantity = (int) $input['quantity'];
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Điều chỉnh tồn kho thủ công';
        }

        $referenceId = isset($input['referenceId']) ? trim((string) $input['referenceId']) : null;

        $stock = $this->driver->adjust($variantExternalId, $quantity, $operation, $reason, $referenceId !== '' ? $referenceId : null);

        return Response::json([
            'success' => true,
            'data' => $stock,
        ]);
    }

    private function error(string $message, int $status): Response
    {
        return Response::json(['success' => false, 'error' => $message], $status);
    }
}
