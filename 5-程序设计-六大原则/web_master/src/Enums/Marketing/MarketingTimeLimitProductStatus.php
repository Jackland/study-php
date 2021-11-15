<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class MarketingTimeLimitProductStatus extends BaseEnum
{
    const NO_RELEASE = 1; // 1未释放库存
    const RELEASED = 10;  // 10已释放库存

}
