<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class BillOrderType extends BaseEnum
{
    const TYPE_SALES_ORDER = 1; //订单类型1-销售订单

    public static function getViewItems()
    {
        return [
            self::TYPE_SALES_ORDER => 'sales order id'
        ];
    }
}
