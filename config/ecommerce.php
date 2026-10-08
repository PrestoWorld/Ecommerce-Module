<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('NHANH_API_TABLE_PREFIX')
        ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    /*
     * Headless-CMS exposure: the business id whose POS data is published,
     * plus the API key store used for write operations.
     */
    'headless' => [
        'business_id' => getenv('ECOMMERCE_HEADLESS_BUSINESS_ID')
            ?: (getenv('NHANH_SYNC_BUSINESS_ID') ?: ''),
    ],

    'upstream' => [
        'version' => 'v3.0',
        'timeout' => 30,
        'hosts' => [
            'pos' => [
                'base_url' => getenv('NHANH_POS_BASE_URL') ?: 'https://pos.open.nhanh.vn',
            ],
            'vpage' => [
                'base_url' => getenv('NHANH_VPAGE_BASE_URL') ?: 'https://vpage.open.nhanh.vn',
            ],
            'ship' => [
                'base_url' => getenv('NHANH_SHIP_BASE_URL') ?: 'https://ship.open.nhanh.vn',
            ],
            'affiliate' => [
                'base_url' => getenv('NHANH_AFFILIATE_BASE_URL') ?: 'https://affiliate.open.nhanh.vn',
            ],
        ],
    ],
];