<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class FeeOrderStatus extends BaseEnum
{
    const WAIT_PAY = 0;
    const COMPLETE = 5;
    const EXPIRED = 7;
    const REFUND = 8;

    public static function getViewItems()
    {
        return [
            static::WAIT_PAY => 'To be Paid',
            static::COMPLETE => 'Completed',
            static::EXPIRED => 'Canceled',
            static::REFUND => 'Refunded',
        ];
    }
}
