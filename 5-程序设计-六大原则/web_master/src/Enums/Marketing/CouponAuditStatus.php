<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CouponAuditStatus extends BaseEnum
{
    const WAIT = 0; // 待审核
    const PASS = 1; // 审核通过
    const REJECT = 2; // 被驳回
}
