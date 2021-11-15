<?php

namespace App\Catalog\Enums\Stock;

use Framework\Enum\BaseEnum;

class StockBlockReasonTypeEnum extends BaseEnum
{
    const CANCEL_NOT_APPLY_RMA = '1-1';
    const APPLY_RMA_NOT_AGREE_SALES_ORDER = '2-1';
    const APPLY_RMA_NOT_AGREE_PURCHASE_ORDER = '2-2';
    const WAIT_PAY_FEE_ORDER = '3-1';
    const ASR_WAIT_PAY = '4-1';
    const SELL_NOT_SHIPPED_SALES_ORDER = '5-1';
    const SELL_NOT_SHIPPED_RESHIPPED_ORDER = '5-2';
    const INVENTORY_REDUCTION = '6-1';
    const INVENTORY_LOSS = '6-2';
    const INVENTORY_PRE_LOCK_SALES_ORDER = '7-1';

    public static function getViewItems()
    {
        return [
            // 取消销售单未申请RMA
            static::CANCEL_NOT_APPLY_RMA => 'Sales Order',
            // 取消销售单申请RMA未同意
            static::APPLY_RMA_NOT_AGREE_SALES_ORDER => 'RMA',
            // 采购单申请RMA未同意
            static::APPLY_RMA_NOT_AGREE_PURCHASE_ORDER => 'RMA',
            // 销售单已售未发
            static::SELL_NOT_SHIPPED_SALES_ORDER => 'Sales Order',
            // 重发单已售未发
            static::SELL_NOT_SHIPPED_RESHIPPED_ORDER => 'Reshipment Order',
            // 销售单（费用单待支付）
            static::WAIT_PAY_FEE_ORDER => 'Sales Order',
            // 销售单 (ASR待支付)
            static::ASR_WAIT_PAY => 'Sales Order',
            // 库存下调
            static::INVENTORY_REDUCTION => 'Inventory Reduction',
            // 库存盘亏
            static::INVENTORY_LOSS => 'Inventory Loss',
            // 预绑定
            static::INVENTORY_PRE_LOCK_SALES_ORDER => 'Sales Order',
        ];
    }
}
