<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingTimeLimitConfig extends BaseEnum
{
    //限时活动通用折扣
    const NOT_ON_SALE_CODE = 80001; // 有的未上架
    const NOT_ENOUGH_STOCK_CODE = 80002; // 没有足够库存

    const KEY_MD5 = 'time_limit_discount';

}
