<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingDiscountStatus extends BaseEnum
{
    //非数据库配置，自定义
    const ACTIVE = 1; // 生效中
    const PENDING = 2;  // 待生效
    const INVALID = 3;  // 已失效

    public static function getViewItems()
    {
        return [
            static::ACTIVE => __('进行中', [], 'catalog/view/customerpartner/marketing_campaign/discount'),
            static::PENDING => __('待开启', [], 'catalog/view/customerpartner/marketing_campaign/discount'),
            static::INVALID => __('已结束', [], 'catalog/view/customerpartner/marketing_campaign/discount'),
        ];
    }

    //页面上有颜色区分
    public static function getColorItems()
    {
        return [
            static::ACTIVE => 'oris-bg-success',
            static::PENDING => 'oris-bg-warning',
            static::INVALID => 'oris-bg-info',
        ];
    }

    public static function getColorDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getColorItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }

}
