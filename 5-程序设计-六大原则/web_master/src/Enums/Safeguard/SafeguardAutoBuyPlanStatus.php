<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class SafeguardAutoBuyPlanStatus extends BaseEnum
{
    const EFFECTIVE = 1; // 生效
    const TERMINATION = 2;  // 终止
    const COMPLETED = 30;  // 已完成   非数据库定义，配置自定义

    public static function getViewItems()
    {
        return [
            self::EFFECTIVE => 'Active',
            self::TERMINATION => 'Terminated',
            self::COMPLETED => 'Completed',
        ];
    }
}
