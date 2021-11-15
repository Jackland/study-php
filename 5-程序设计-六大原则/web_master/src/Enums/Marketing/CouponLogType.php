<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CouponLogType extends BaseEnum
{
    const CREATED = 1; // 新建
    const EDIT = 2; // 编辑
    const PASS = 3; // 通过
    const REJECT = 4; // 驳回
}
