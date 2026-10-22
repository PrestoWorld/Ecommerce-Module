<?php

declare(strict_types=1);

/**
 * Table prefix for the Payment module.
 *
 * The migrations of this module run with PW_TABLE_PREFIX, so the repository
 * has to resolve the same prefix to find its tables. The module specific env
 * var wins when it is set.
 */
return [
    'table_prefix' => getenv('PAYMENT_TABLE_PREFIX') ?: (getenv('PW_TABLE_PREFIX') ?: 'pw_'),
];
