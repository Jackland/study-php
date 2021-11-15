<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingTimeLimitStatus extends BaseEnum
{
    //只有10是数据库字段 有顺序关系
    const ACTIVE = 2; // 进行中
    const PENDING = 4;  // 待开启
    const EXPIRED = 6;  // 已结束
    const STOPED = 10;  // 已停止

    public static function getViewItems()
    {
        return [
            static::ACTIVE => __('进行中', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            static::PENDING => __('待开启', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            static::EXPIRED => __('已结束', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            static::STOPED => __('已停止', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
        ];
    }

    //页面上有颜色区分
    public static function getColorItems()
    {
        return [
            static::ACTIVE => 'oris-bg-success',
            static::PENDING => 'oris-bg-warning',
            static::EXPIRED => 'oris-bg-info',
            static::STOPED => 'oris-bg-info',
        ];
    }

    public static function getColorDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getColorItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }

    public static function getUnEffectiveStatus()
    {
        return [self::EXPIRED, self::STOPED];
    }

}
