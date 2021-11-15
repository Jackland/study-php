<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

class SafeguardClaimStatus extends BaseEnum
{
    const CLAIM_IN_PROGRESS = 10;//理赔中
    const CLAIM_BACKED = 11; //资料待完善
    const CLAIM_SUCCEED = 20; //理赔成功
    const CLAIM_FAILED = 30; //理赔失败

    public static function getViewItems()
    {
        return [
            static::CLAIM_IN_PROGRESS => 'Claim in Progress',
            static::CLAIM_BACKED => 'Info to be Added',
            static::CLAIM_SUCCEED => 'Succeeded',
            static::CLAIM_FAILED => 'Failed',
        ];
    }

    //页面上有颜色区分
    public static function getColorItems()
    {
        return [
            static::CLAIM_IN_PROGRESS => 'oris-bg-default',
            static::CLAIM_BACKED => 'oris-bg-warning',
            static::CLAIM_SUCCEED => 'oris-bg-success',
            static::CLAIM_FAILED => 'oris-bg-info',
        ];
    }

    public static function getColorDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getColorItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }

    //当前状态，不允许申请理赔
    public static function canNotApplyClaimStatus()
    {
        return [self::CLAIM_IN_PROGRESS, self::CLAIM_BACKED];
    }

}
