<?php

namespace App\Enums\Future;

use App\Models\Futures\FuturesMarginProcess;
use Framework\Enum\BaseEnum;

/**
 * Class FuturesMarginProcessStatus
 * @package App\Enums\Future
 * @see FuturesMarginProcess
 */
class FuturesMarginProcessStatus extends BaseEnum
{
    const APPROVED = 1;   // 审批通过 头款商品创建采购
    const ADVANCE_BUY = 2; // 头款产品购买采购成功，尾款产品创建成功
    const TAIL_SOLD = 3; // 尾款产品支付分销中
    const COMPLETED = 4; // 所有尾款产品销售完成
}
