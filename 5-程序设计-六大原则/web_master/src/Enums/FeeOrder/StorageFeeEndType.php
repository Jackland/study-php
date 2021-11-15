<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class StorageFeeEndType extends BaseEnum
{
    const SALE = 1;
    const RMA = 2;
    const BO = 3;//BO出库 库存盘亏
    const FBA = 4;//FBA出库 库存下调
    const SHOULD_NOT = 5;//不应收取仓租
    const MARGIN_TERMINATED = 6;//现货协议到期

    public static function getViewItems()
    {
        return [
            static::SALE => '销售订单出库',
            static::RMA => '采购订单RMA退货',
            static::BO => '库存盘亏',
            static::FBA => '库存下调',
            static::SHOULD_NOT => '不应收取仓租',
            static::MARGIN_TERMINATED => '现货协议到期buyer违约',
        ];
    }
}
