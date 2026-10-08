<?php

declare(strict_types=1);

return [
    'table_prefix' => getenv('CATALOG_TABLE_PREFIX')
        ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),

    /*
     * Headless-CMS exposure: the business id whose Catalog data is published,
     * plus the API key store used for write operations.
     */
    'headless' => [
        'business_id' => getenv('CATALOG_HEADLESS_BUSINESS_ID') ?: '',
    ],
];