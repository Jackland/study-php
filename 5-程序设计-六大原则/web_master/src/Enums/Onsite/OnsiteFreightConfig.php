<?php

namespace App\Enums\Onsite;

use Framework\Enum\BaseEnum;

class OnsiteFreightConfig extends BaseEnum
{
    const LASTED_RECORD_DATE = '2099-01-01 00:00:00'; // 约定值
    const GIGAONSITE_CODE_NOT_CONFIG = 507;  // 没配置报价
    const GIGAONSITE_CODE_NOT_CONFIG_LTL = 508;// JAVA那边额外扩展的
    const GIGAONSITE_CODE_CONFIG_NO_RESULT = 500; // 配置了算不出来

    public static function getGigaOnsiteIllegalCode()
    {
        return [self::GIGAONSITE_CODE_NOT_CONFIG, self::GIGAONSITE_CODE_CONFIG_NO_RESULT, self::GIGAONSITE_CODE_NOT_CONFIG_LTL];
    }

}
