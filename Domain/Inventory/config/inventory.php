<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('INVENTORY_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'location' => [
        'default_type' => 'warehouse',
        'types' => ['warehouse', 'store', 'dropship', 'virtual'],
    ],

    'stock' => [
        'default_reorder_point' => 10,
        'default_reorder_quantity' => 100,
        'low_stock_threshold_percent' => 0.2,
        'auto_create_on_adjust' => true,
        'track_movements' => true,
    ],

    'reservation' => [
        'default_ttl_minutes' => 30,
        'auto_release_on_expiry' => true,
    ],
];