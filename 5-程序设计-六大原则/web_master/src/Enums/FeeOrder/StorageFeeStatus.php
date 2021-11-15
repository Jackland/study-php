<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class StorageFeeStatus extends BaseEnum
{
    const WAIT = 10;
    const BIND = 20;
    const COMPLETED = 30;

    public static function getViewItems()
    {
        return [
            static::WAIT => '待绑定',
            static::BIND => '已绑定',
            static::COMPLETED => '已完成',
        ];
    }

    /**
     * 需要计算仓租的状态
     * @return array
     */
    public static function needCalculateStatus()
    {
        return [
            static::WAIT,
            static::BIND,
        ];
    }

    /**
     * 可以绑定的状态
     * @return array
     */
    public static function canBindStatus()
    {
        return [static::WAIT];
    }

    /**
     * 可以解绑的状态
     * @return array
     */
    public static function canUnbindStatus()
    {
        return [static::BIND];
    }
}
