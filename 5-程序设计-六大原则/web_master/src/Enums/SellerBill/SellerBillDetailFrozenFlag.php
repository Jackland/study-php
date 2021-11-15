<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算明细冻结状态
 * tb_seller_bill_detail --> frozen_flag
 *
 * Class SellerBillDetailFrozenFlag
 * @package App\Enums\SellerBill
 */
class SellerBillDetailFrozenFlag extends BaseEnum
{
    const NOT_NEED_FROZEN = 0; // 无需冻结
    const FROZEN = 1; // 已冻结
    const UNFROZEN = 2; // 已解冻

    public static function getViewItems()
    {
        return [
            self::NOT_NEED_FROZEN => __('无需冻结', [], 'enums/bill'),
            self::FROZEN => __('冻结中', [], 'enums/bill'),
            self::UNFROZEN => __('已解冻', [], 'enums/bill'),
        ];
    }
}