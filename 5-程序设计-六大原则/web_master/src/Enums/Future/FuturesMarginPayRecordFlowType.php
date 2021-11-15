<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

class FuturesMarginPayRecordFlowType extends BaseEnum
{
    const SELLER_DEPOSIT_EXPAND = 1;   // seller保证金支出
    const SELLER_DEPOSIT_INCOME = 2; // seller保证金返还
    const SELLER_DEFAULT_EXPAND = 3; // seller违约金支出
    const SELLER_MARKETPLACE_EXPAND = 4; // seller支付平台费
    const BUYER_DEFAULT_INCOME = 5; // buyer违约金返还
    const buyer_deposit_income = 6; // 返还buyer保证金


}
