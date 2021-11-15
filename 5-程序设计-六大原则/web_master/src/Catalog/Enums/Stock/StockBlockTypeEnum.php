<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

class StockBlockTypeEnum extends BaseEnum
{
    const CANCEL_ORDER_NOT_APPLY_RMA = 1;
    const SELLER_NOT_AGREE_RMA = 2;
    const WAIT_FOR_FEE_ORDER = 3;
    const ASR = 4;
    const SELL_NOT_SHIPPED = 5;
    const PRODUCT_LOCK = 6;
    const PRE_LOCK = 7;

    public static function getViewItems()
    {
        return [
            static::CANCEL_ORDER_NOT_APPLY_RMA => 'Order canceled without RMA request',
            static::SELLER_NOT_AGREE_RMA => 'RMA approval pending',
            static::SELL_NOT_SHIPPED => 'Unshipped',
            static::WAIT_FOR_FEE_ORDER => 'Pending charges',
            static::ASR => 'ASR to be paid',
            static::PRODUCT_LOCK => 'Buyer Inventory Adjustment',
            static::PRE_LOCK => 'Pre-locked',
        ];
    }
}
