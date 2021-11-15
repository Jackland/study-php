<?php

namespace App\Enums\Future;

use App\Models\Futures\FuturesMarginProcess;
use Framework\Enum\BaseEnum;

/**
 * Class FuturesMarginProcessStatus
 * @package App\Enums\Future
 * @see FuturesMarginProcess
 */
class FuturesMarginApplyStatus extends BaseEnum
{
    const PENDING = 0;   // 待审批
    const PASS = 1; // 审批通过
    const REJECT = 2; // 审批拒绝
    const TIMEOUT = 3; // 超时
}
