<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * 现货协议申请共同履约人
 *
 * Class MarginPerformerApplyStatus
 * @package App\Enums\Margin
 */
class MarginPerformerApplyStatus extends BaseEnum
{
    const PENDING = 0;//未处理
    const APPROVED = 1;//同意
    const REJECTED = 2;//拒绝

    public static function getViewItems()
    {
        return [
            static::PENDING => 'Pending',
            static::APPROVED => 'Approved',
            static::REJECTED => 'Rejected',
        ];
    }

    /**
     * 未拒绝
     * @return int[]
     */
    public static function noRejected()
    {
        return [self::PENDING, self::APPROVED];
    }

}
