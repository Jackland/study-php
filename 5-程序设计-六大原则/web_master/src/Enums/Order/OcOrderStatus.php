<?php

namespace App\Enums\Order;

use Framework\Enum\BaseEnum;

/**
 * oc_order.order_status_id
 * 参考oc_order_status表
 *
 * Class OcOrderStatus
 * @package App\Enums\Order
 */
class OcOrderStatus extends BaseEnum
{
    const TO_BE_PAID = 0;
    const PENDING = 1;
    const PROCESSING = 2;
    const SHIPPED = 3;
    const COMPLETED = 5;
    const CANCELED = 7;
    const DENIED = 8;
    const CANCELED_REVERSAL = 9;
    const FAILED = 10;
    const REFUNDED = 11;
    const REVERSED = 12;
    const CHARGEBACK = 13;
    const EXPIRED = 14;
    const PROCESSED = 15;
    const VOIDED = 16;

    public static function getViewItems()
    {
        return [
            static::TO_BE_PAID => 'To Be Paid',
            static::PENDING => 'Pending',
            static::PROCESSING => 'Processing',
            static::SHIPPED => 'Shipped',
            static::COMPLETED => 'Completed',
            static::CANCELED => 'Canceled',
            static::DENIED => 'Denied',
            static::CANCELED_REVERSAL => 'Canceled Reversal',
            static::FAILED => 'Failed',
            static::REFUNDED => 'Refunded',
            static::REVERSED => 'Reversed',
            static::CHARGEBACK => 'Chargeback',
            static::EXPIRED => 'Expired',
            static::PROCESSED => 'Processed',
            static::VOIDED => 'Voided',
        ];
    }
}
