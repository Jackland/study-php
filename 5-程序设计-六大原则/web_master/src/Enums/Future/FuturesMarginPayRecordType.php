<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

class FuturesMarginPayRecordType extends BaseEnum
{
    const LINE_OF_CREDIT = 1;   // 授信额度
    const SELLER_BILL = 3; // 应收款
    const SELLER_COLLATERAL = 4; // 抵押物

    /**
     * 外部seller支付类型
     * @return int[]
     */
    public static function oAccountingPayType()
    {
        return [self::SELLER_BILL, self::SELLER_COLLATERAL];
    }
}
