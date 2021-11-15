<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class SafeguardBillStatus extends BaseEnum
{
    const ACTIVE = 1; // 保障中
    const CANCELED = 2;  // 已取消
    const PENDING = 3;  // 待生效
    const INVALID = 40;  // 已失效（非数据库配置，自定义）

    public static function getViewItems()
    {
        return [
            static::PENDING => 'Pending',
            static::ACTIVE => 'Active',
            static::INVALID => 'Invalid',
            static::CANCELED => 'Canceled',
        ];
    }

    //页面上有颜色区分
    public static function getColorItems()
    {
        return [
            static::PENDING => 'oris-bg-default',
            static::INVALID => 'oris-bg-info',
            static::ACTIVE => 'oris-bg-success',
            static::CANCELED => 'oris-bg-info',
        ];
    }

    public static function getColorDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getColorItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }

    /**
     * 可以申请理赔对应的状态
     * @return array
     */
    public static function canApplyClaimInStatus()
    {
        return [
            self::ACTIVE,
        ];
    }
}
