<?php

declare(strict_types=1);

return [
    'currency' => [
        'default' => 'VND',
        'supported' => ['VND', 'USD', 'EUR'],
        'precision' => 0, // VND has no decimals
    ],
    'phone' => [
        'default_country' => 'VN',
        'format' => 'national', // national, international, e164
    ],
    'address' => [
        'default_country' => 'VN',
    ],
];