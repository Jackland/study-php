<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CouponStatus extends BaseEnum
{
    const UNUSED = 1; // 优惠券未使用
    const USED = 2; // 优惠券已使用
    const INVALID = 3; // 优惠券已经无效
}
