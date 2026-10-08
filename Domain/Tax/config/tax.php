<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('TAX_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    'default_business_id' => getenv('TAX_DEFAULT_BUSINESS_ID') ?: '',

    'default_rates' => [
        [
            'code' => 'VAT',
            'name' => 'VAT 10%',
            'rate' => 10.0,
            'type' => 'percentage',
            'scope' => 'national',
            'priority' => 10,
            'is_active' => true,
        ],
    ],

    'calculation' => [
        'precision' => 0, // VND
        'rounding' => 'half_up', // half_up, half_down, half_even
    ],
];