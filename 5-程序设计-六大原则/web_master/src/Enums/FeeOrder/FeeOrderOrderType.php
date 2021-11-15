<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class FeeOrderOrderType extends BaseEnum
{
    const SALES = 1;
    const RMA = 2;
    const ORDER = 3; // 采购单

    public static function getViewItems()
    {
        return [
            self::SALES => '销售单',
            self::RMA => 'RMA',
            self::ORDER => '采购单',
        ];
    }

    public static function getViewItemsAlias()
    {
        return [
            self::SALES => 'order_sales',
            self::RMA => 'order_rma',
            self::ORDER => 'order',
        ];
    }
}
