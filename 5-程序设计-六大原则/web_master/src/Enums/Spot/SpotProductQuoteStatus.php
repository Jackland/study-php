<?php

namespace App\Enums\Spot;

use Framework\Enum\BaseEnum;

class SpotProductQuoteStatus extends BaseEnum
{
    const APPLIED = 0; //待审核
    const APPROVED = 1; //已审核
    const REJECTED = 2; //已拒绝
    const SOLD = 3; //已购买
    const TIMEOUT = 4; //超时
    const CANCELED = 5; //用户取消

    public static function getViewItems()
    {
        return [
            self::APPLIED => 'Applied',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SOLD => 'Sold',
            self::TIMEOUT => 'Time out',
            self::CANCELED => 'Canceled',
        ];
    }
}
