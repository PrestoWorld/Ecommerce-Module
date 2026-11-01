<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Http;

use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Domain\Attribute\Contracts\ProductAttributeValueRepositoryInterface;
use Witals\Framework\Application;
use Witals\Framework\Http\Request;
use Witals\Framework\Http\Response;

/**
 * Admin REST API cho thuộc tính sản phẩm (EAV definitions + values).
 *
 * Routes (registered by Ecommerce\Module::boot):
 *   GET    /api/admin/product-attributes            list definitions
 *   POST   /api/admin/product-attributes            create/upsert (theo code)
 *   PUT    /api/admin/product-attributes/{code}     update
 *   DELETE /api/admin/product-attributes/{code}     delete definition
 *   GET    /api/admin/product-attributes/values     list values (filter product/attribute_code/warehouse)
 *   POST   /api/admin/product-attributes/values     create/upsert value
 *   DELETE /api/admin/product-attributes/values/{id} delete value (id = UUID)
 *
 * Bị AdminAuthMiddleware bảo vệ theo prefix /api/admin (path-only).
 */
final class AdminProductAttributesController
{
    private const PREFIX = '/api/admin/product-attributes';
    private const VALUES = '/api/admin/product-attributes/values';

    public function __construct(
        protected Application $app,
        protected ProductAttributeRepositoryInterface $attributes,
        protected ProductAttributeValueRepositoryInterface $values,
    ) {
    }

    public function handle(Request $request, ?string $id = null): Response
    {
        $method = strtoupper($request->method());
        $path = rtrim($request->path(), '/');

        try {
            if ($path === self::VALUES) {
                return match ($method) {
                    'GET' => Response::json($this->values->search($this->businessId(), $this->filters($request), $this->offset($request), $this->limit($request))),
                    'POST' => Response::json($this->values->save($this->businessId(), $this->body($request))),
                    default => Response::json(['error' => 'Method not allowed'], 405),
                };
            }

            if (str_starts_with($path, self::VALUES . '/')) {
                $valueId = (string) substr($path, strlen(self::VALUES . '/'));
                if ($method === 'DELETE') {
                    $deleted = $this->values->delete($this->businessId(), $valueId);

                    return Response::json(['deleted' => $deleted], $deleted ? 200 : 404);
                }

                return Response::json(['error' => 'Method not allowed'], 405);
            }

            if ($path === self::PREFIX) {
                return match ($method) {
                    'GET' => Response::json($this->attributes->search($this->businessId(), $this->filters($request), $this->offset($request), $this->limit($request))),
                    'POST' => Response::json($this->attributes->save($this->businessId(), $this->body($request))),
                    default => Response::json(['error' => 'Method not allowed'], 405),
                };
            }

            if ($id !== null && $id !== '') {
                // {code} trên route là business key của definition.
                if ($method === 'DELETE') {
                    $deleted = $this->attributes->delete($this->businessId(), $id);

                    return Response::json(['deleted' => $deleted], $deleted ? 200 : 404);
                }

                return match ($method) {
                    'PUT', 'PATCH' => Response::json($this->attributes->save($this->businessId(), ['code' => $id] + $this->body($request))),
                    default => Response::json(['error' => 'Method not allowed'], 405),
                };
            }

            return Response::json(['error' => 'Not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Internal error: ' . $e->getMessage()], 500);
        }
    }

    private function body(Request $request): array
    {
        $body = $request->getJson();

        return is_array($body) ? $body : [];
    }

    private function filters(Request $request): array
    {
        $filters = [];
        foreach (['code', 'type', 'status', 'attribute_code', 'product_external_id', 'warehouse_code'] as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }

    private function offset(Request $request): int
    {
        return (max(1, (int) $request->query('page', 1)) - 1) * $this->limit($request);
    }

    private function limit(Request $request): int
    {
        return max(1, min(200, (int) $request->query('per_page', 50)));
    }

    private function businessId(): string
    {
        $value = (string) ($this->app->config('ecommerce.headless.business_id', '') ?: '');
        if ($value === '') {
            $value = (string) ($this->app->config('catalog.headless.business_id', '') ?: '');
        }

        return $value;
    }
}
