<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

class BatchTransactionType extends BaseEnum
{
    const INVENTORY_RECEIVE = 1;
    const INVENTORY_INC = 2;
    const INVENTORY_PROFIT = 3;
   // const TRANSFER_GOODS = 4;
    const RMA_RETURN = 5;
    const OTHER = 6;

    public static function getViewItems()
    {
        return [
            self::INVENTORY_RECEIVE => __('入库单收货', [], 'enums/warehouse'),
            self::INVENTORY_INC => __('库存上调', [], 'enums/warehouse'),
            self::INVENTORY_PROFIT => __('盘盈', [], 'enums/warehouse'),
           // self::TRANSFER_GOODS => __('调货入库', [], 'enums/warehouse'),
            self::RMA_RETURN => __('RMA退货', [], 'enums/warehouse'),
            self::OTHER => __('其他', [], 'enums/warehouse')
        ];
    }

    public static function getAllTypeArr()
    {
        $allType = self::getValues();
        $allTypeArr = [];
        foreach ($allType as $value) {
            $item['value'] = $value;
            $item['key'] = self::getDescription($value);
            $allTypeArr[] = $item;
        }

        return $allTypeArr;
    }

    // 库存调整的类型 - 库存上调、盘盈
    public static function getChangeType()
    {
        return [
            self::INVENTORY_INC,
            self::INVENTORY_PROFIT
        ];
    }

    // 其他类型 - 调货入库、其他
    public static function getOtherType()
    {
        return [
        //    self::TRANSFER_GOODS,
            self::OTHER
        ];
    }
}
