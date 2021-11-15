<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_seller_delivery_line --> type
 *
 * Class SellerDeliveryLineType
 * @package App\Enums\Warehouse
 */
class SellerDeliveryLineType extends BaseEnum
{
    const PURCHASE_ORDER = 1;
    const RMA = 2;
    const OTHER = 9;
    const REDUCE_STOCK = 5;
    const INVENTORY_LOSSES = 6;

    public static function getViewItems()
    {
        return [
            self::PURCHASE_ORDER => __('采购订单出库', [], 'enums/warehouse'),
            self::RMA => __('RMA重发', [], 'enums/warehouse'),
            self::OTHER => __('其他', [], 'enums/warehouse'),
            self::REDUCE_STOCK => __('库存下调', [], 'enums/warehouse'),
            self::INVENTORY_LOSSES => __('盘亏', [], 'enums/warehouse'),
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

    // 关联Ajuest表查询类型 - 库存下调、盘亏
    public static function getChangeType()
    {
        return [
            self::REDUCE_STOCK,
            self::INVENTORY_LOSSES
        ];
    }

    // 本表查询类型（无需关联其他表）-
    public static function getOtherType()
    {
        return [
            self::PURCHASE_ORDER,
            self::OTHER,
        ];
    }

    // 带备注筛选无效类型
    public static function getInvalidRemarkType()
    {
        return [
            self::OTHER,
            self::REDUCE_STOCK,
            self::INVENTORY_LOSSES
        ];
    }
}
