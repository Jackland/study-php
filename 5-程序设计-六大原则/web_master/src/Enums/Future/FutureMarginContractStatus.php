<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

class FutureMarginContractStatus extends BaseEnum
{
    const SALE = 1;
    const DISABLE = 2;
    const COMPLETED = 3;
    const TERMINATE = 4;

    public static function getViewItems()
    {
        return [
            self::SALE => 'Active',
            self::DISABLE => 'Disabled',
            self::COMPLETED => 'Sold Out',
            self::TERMINATE => 'Terminated',
        ];
    }
}
