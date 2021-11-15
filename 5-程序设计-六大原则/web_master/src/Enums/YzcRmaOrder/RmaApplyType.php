<?php

namespace App\Enums\YzcRmaOrder;

use Framework\Enum\BaseEnum;

class RmaApplyType extends BaseEnum
{
    const RESHIP = 1; // 退款
    const REFUND = 2; // 重发
    const RESHIP_AND_REFUND = 3; // 退款又重发

    /**
     * [2, 3]
     * @return int[]
     */
    public static function getRefund()
    {
        return [static::REFUND, static::RESHIP_AND_REFUND];
    }
}
