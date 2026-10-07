<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Ship;

use PrestoWorld\Modules\Ecommerce\Contracts\ShipDirectoryRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractDirectoryRepository;

final class ShippingDirectoryRepository extends AbstractDirectoryRepository implements ShipDirectoryRepositoryInterface
{
    protected function branch(): string
    {
        return 'ship';
    }
}