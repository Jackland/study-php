<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CampaignType extends BaseEnum
{
    const OTHER = 0;
    const BANNER = 1;
    const FULL_DELIVERY = 2; //满送
    const FULL_REDUCTION = 3; //满减

    /**
     * 满减/送类型
     * @return int[]
     */
    public static function fullTypes()
    {
        return [
            self::FULL_REDUCTION, self::FULL_DELIVERY
        ];
    }

    /**
     * 满减/送类型对应活动的内容
     * @return string[]
     */
    public static function fullTypesPromotionContentMap()
    {
        return [
            self::FULL_REDUCTION => 'Save {promotion} for every {order_amount} spent',
            self::FULL_DELIVERY => 'Get {promotion} for next purchase order over {order_amount}',
        ];
    }
}
