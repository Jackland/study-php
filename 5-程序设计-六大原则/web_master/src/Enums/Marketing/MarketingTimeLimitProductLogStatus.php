<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingTimeLimitProductLogStatus extends BaseEnum
{
    const ADD = 10; // 创建增加活动库存
    const INCR = 15;  // 补充活动动库存
    const ABANDONED = 20;  // 废弃
    const LOCKED = 30;  // 锁定
    const FINISHED = 40; // 完成

}
