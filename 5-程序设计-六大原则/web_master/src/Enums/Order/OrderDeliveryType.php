<?php

namespace App\Enums\Order;

use Framework\Enum\BaseEnum;

/**
 * 订单发货类型
 *
 * Class OrderDeliveryType
 * @package App\Enums\Order
 */
class OrderDeliveryType extends BaseEnum
{
    const DROP_SHIPPING = 0; // 一件代发
    const WILL_CALL = 1; // 上门取货
    const CWF = 2; // 云送仓

    public static function getViewItems()
    {
        return [
            self::DROP_SHIPPING => __('一件代发', [], 'enums/order'),
            self::WILL_CALL => __('上门取货', [], 'enums/order'),
            self::CWF => __('云送仓', [], 'enums/order')
        ];
    }
}
