<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Pos;

use PrestoWorld\Modules\Ecommerce\Contracts\ShippingDirectoryRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractDirectoryRepository;

final class ShippingDirectoryRepository extends AbstractDirectoryRepository implements ShippingDirectoryRepositoryInterface
{
    protected function branch(): string
    {
        return 'pos';
    }
}