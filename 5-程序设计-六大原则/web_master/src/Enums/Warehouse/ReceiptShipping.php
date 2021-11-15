<?php


namespace App\Enums\Warehouse;


use Framework\Enum\BaseEnum;

class ReceiptShipping extends BaseEnum
{
    const ENTRUSTED_SHIPPING = 1; // 运输方式： 委托海运操作
    const MY_SELF = 2; // 运输方式：客户自发
    const B2B_LOCAL = 3; // 运输方式：B2B Local

    public static function getViewItems()
    {
        return [
            self::ENTRUSTED_SHIPPING => __('委托海运操作', [], 'enums/warehouse'),
            self::MY_SELF => __('客户自发', [], 'enums/warehouse'),
            self::B2B_LOCAL => __('本土发货', [], 'enums/warehouse'),
        ];
    }
}
