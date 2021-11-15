<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

/**
 * oc_futures_margin_delivery delivery_status
 *
 * Class FuturesMarginDeliveryStatus
 * @package App\Enums\Future
 */
class FuturesMarginDeliveryStatus extends BaseEnum
{
    const TO_BE_DELIVERED = 1; // 等待seller交付产品
    const DELIVERY_FAILED = 2; // Seller违约,交付失败
    const BEING_PROCESSED = 3;
    const UN_EXECUTED = 4; // buyer违约
    const PROCESSING = 5;
    const TO_BE_PAID = 6; //　等待buyer交割;支付尾款
    const COMPLETED = 8; // 协议完成
    const TERMINATED = 9; // 协议终止

    public static function getViewItems()
    {
        return [
            static::TO_BE_PAID => 'To be Paid'
        ];
    }
}
