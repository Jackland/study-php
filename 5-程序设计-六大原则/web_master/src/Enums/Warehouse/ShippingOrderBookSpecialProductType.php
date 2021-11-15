<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

/**
 * 入库单托书贸易方式
 * tb_sys_receipts_order_shipping_order_book.special_product_type
 *
 * Class ShippingOrderBookSpecialProductType
 * @package App\Enums\Warehouse
 */
class ShippingOrderBookSpecialProductType extends BaseEnum
{
    const DANGEROUS_GOODS = 1;
    const ANTI_DUMPING = 2;
    const BATTERY_INCLUDED = 3;

    public static function getViewItems()
    {
        return [
            self::DANGEROUS_GOODS => 'Dangerous Goods',
            self::ANTI_DUMPING => 'Anti-dumping',
            self::BATTERY_INCLUDED => 'Battery Included',
        ];
    }

    /**
     * 海运系统的对应描述选项
     *
     * @return array
     */
    private static function getOceanViewItems()
    {
        return [
            self::DANGEROUS_GOODS => 'DGR',
            self::ANTI_DUMPING => 'AD',
            self::BATTERY_INCLUDED => 'WB',
        ];
    }

    /**
     * 获取海运系统特殊商品描述
     *
     * @param $typeStr
     * @return string
     */
    public static function getOceanDesc($typeStr)
    {
        $oceanStr = '';

        $oceanView = self::getOceanViewItems();
        $typeArr = explode(',', $typeStr);
        foreach ($typeArr as $item) {
            $oceanStr .= $oceanView[$item] . ',';
        }

        return trim($oceanStr, ',');
    }
}
