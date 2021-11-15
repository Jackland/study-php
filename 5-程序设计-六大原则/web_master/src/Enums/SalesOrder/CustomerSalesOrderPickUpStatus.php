<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

/**
 * 销售订单状态
 * 对应 tb_sys_dictionary 表下的 CUSTOMER_ORDER_PICK_UP_STATUS 的值
 */
class CustomerSalesOrderPickUpStatus extends BaseEnum
{
    const DEFAULT = 0; // 默认初始值
    const PICK_UP_INFO_TBC = 10;//取货信息待确认 Pick-up Info TBC
    const IN_PREP = 20;//仓库备货中 In Prep
    const PICK_UP_TIMEOUT = 30;//超时未取货 Pick-up Timeout

    public static function getViewItems()
    {
        return [
            static::PICK_UP_INFO_TBC => 'Pick-up Info TBC',
            static::IN_PREP => 'In Prep',
            static::PICK_UP_TIMEOUT => 'Pick-up Timeout',
        ];
    }

    /**
     * 销售订单BP状态下的子状态
     * @return array
     */
    public static function bpSubState()
    {
        return [
            static::PICK_UP_INFO_TBC,
            static::IN_PREP,
        ];
    }

    /**
     * 销售订单on hold状态下的子状态
     * @return array
     */
    public static function onHoldSubState()
    {
        return [
            static::PICK_UP_TIMEOUT,
        ];
    }

    /**
     * 页面对应状态颜色
     * @return array
     */
    public static function getColorItems()
    {
        return [
            static::PICK_UP_INFO_TBC => '#CCE6CC',
            static::IN_PREP => '#CCE6CC',
            static::PICK_UP_TIMEOUT => '#E5E5E5',
        ];
    }

    public static function getColorDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getColorItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }
}
