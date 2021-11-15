<?php

namespace App\Enums\ModifyLog;

use Framework\Enum\BaseEnum;

class CommonOrderActionStatus extends BaseEnum
{
    const PENDING = 1; //操作状态 1:操作中,2:成功,3:失败
    const SUCCESS = 2;
    const FAILED = 3;
}
