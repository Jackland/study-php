<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class SafeguardSalesOrderErrorLogType extends BaseEnum
{
    const INSUFFICIENT_BALANCE = 1; //余额不足

    public static function getViewItems()
    {
        return [
            self::INSUFFICIENT_BALANCE => '余额不足'
        ];
    }
}
