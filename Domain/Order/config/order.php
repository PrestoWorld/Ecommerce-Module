<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('ORDER_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'order' => [
        'code_prefix' => 'ORD',
        'code_format' => 'Ymd-{random}',
        'default_status' => 'draft',
        'auto_confirm' => false,
    ],

    'cart' => [
        'ttl_days' => 30,
        'max_items' => 100,
    ],

    'quote' => [
        'code_prefix' => 'QUOT',
        'validity_days' => 30,
        'auto_expire' => true,
    ],
];