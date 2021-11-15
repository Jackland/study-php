<?php

namespace App\Enums\Marketing;

use App\Enums\BaseEnum;

class MarketingTimeLimitProductLogStatus extends BaseEnum
{
    const FINISHED = 10; // 完成
    const ABANDONED = 20;  // 废弃
    const LOCKED = 30;  // 锁定

}
