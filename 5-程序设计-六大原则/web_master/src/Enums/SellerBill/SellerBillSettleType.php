<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 账单结算方式
 * tb_seller_bill --> settle_type
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerBillSettleType extends BaseEnum
{
    const DIRECT_SETTLEMENT = 0; // 直接结算
    const SWITCH_BALANCE = 1; // 转期初余额
}