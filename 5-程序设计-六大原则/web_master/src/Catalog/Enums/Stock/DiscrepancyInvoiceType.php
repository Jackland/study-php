<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

/**
 * 入出库流水单据类型
 *
 * Class DiscrepancyInvoiceType
 * @package App\Enums\Stock
 */
class DiscrepancyInvoiceType extends BaseEnum
{
    const SALES_ORDER = 1;//销售出库
    const PURCHASE_ORDER = 2;//采购入库
    const RMA = 3;//退货出库
    const RESHIPMENT_ORDER = 4;//重发 入库/出库
    const BLOCKED = 5; // buyer库存锁定

    public static function getViewItems()
    {
        return [
            static::SALES_ORDER => 'Sales Order',
            static::PURCHASE_ORDER => 'Purchase Order',
            static::RMA => 'RMA',
            static::RESHIPMENT_ORDER => 'Reshipment Order',
            static::BLOCKED => 'Buyer Inventory Blocked'
        ];
    }
}
