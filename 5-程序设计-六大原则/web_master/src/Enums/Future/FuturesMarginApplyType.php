<?php

namespace App\Enums\Future;

use App\Models\Futures\FuturesMarginProcess;
use Framework\Enum\BaseEnum;

/**
 * Class FuturesMarginProcessStatus
 * @package App\Enums\Future
 * @see FuturesMarginProcess
 */
class FuturesMarginApplyType extends BaseEnum
{
    const APPROVED = 1;   // 提前交付
    const CANCEL = 2; // 取消交付
    const DISCUSS = 3; // 协商终止
    const APPEAL = 4; // 申诉
    const NORMAL = 5; // 正常交付
}
