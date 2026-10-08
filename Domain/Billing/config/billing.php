<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('BILLING_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'invoice' => [
        'number_prefix' => 'INV',
        'number_format' => 'Ymd-{random}',
        'default_due_days' => 30,
        'default_status' => 'draft',
        'auto_issue' => true,
    ],

    'payment' => [
        'number_prefix' => 'PAY',
        'default_method' => 'cash',
        'supported_methods' => ['cash', 'card', 'bank_transfer', 'momo', 'zalopay', 'vnpay', 'cod', 'wallet'],
        'supported_gateways' => ['momo', 'zalopay', 'vnpay', 'payos', 'stripe'],
    ],
];