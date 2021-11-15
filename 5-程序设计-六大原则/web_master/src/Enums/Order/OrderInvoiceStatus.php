<?php

namespace App\Enums\Order;

use Framework\Enum\BaseEnum;

/**
 * 订单发货类型
 *
 * Class OrderDeliveryType
 * @package App\Enums\Order
 */
class OrderInvoiceStatus extends BaseEnum
{
    const GOING = 1; // 生成中
    const FAILURE = 2; // 生成失败
    const SUCCESS = 3; // 生成成功

    public static function getViewItems()
    {
        return [
            self::GOING => 'In progress',
            self::FAILURE => 'Failed',
            self::SUCCESS => 'Completed'
        ];
    }
}
