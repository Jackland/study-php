<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

class StockSearchTypeEnum extends BaseEnum
{
    const AVAILABLE_QTY = 1;
    const AGREEMENT_QTY = 2;
    const LOCK_QTY = 4;
    const FEE_ORDER_WAIT_PAY = 8;

    public static function getViewItems()
    {
        return [
            static::AVAILABLE_QTY => 'Available QTY',
            static::AGREEMENT_QTY => 'Agreement QTY',
            static::LOCK_QTY => 'Blocked QTY',
            static::FEE_ORDER_WAIT_PAY => 'Storage Fee',
        ];
    }
}
