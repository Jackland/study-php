<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CouponTemplateType extends BaseEnum
{
    const BUYER_DRAW = 1; // 领取型
    const BUY_ENOUGH_SEND = 2; // 买够送
    const DIRECT_GRANT_QUOTA = 3; // 直接发放-定额
    const DIRECT_GRANT_NONQUOTA = 4; // 直接发放-非定额
}
