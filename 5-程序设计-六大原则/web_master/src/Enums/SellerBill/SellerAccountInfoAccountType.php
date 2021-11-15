<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算状态
 * tb_sys_seller_account_info --> account_type
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerAccountInfoAccountType extends BaseEnum
{
    const PUBLIC = 1; // 对公账户
    const PRIVATE = 2; // 对私账户
    const P_CARD = 3; // P卡

    public static function getViewItems()
    {
        return [
            self::PUBLIC => __('对公账户', [], 'enums/bill'),
            self::PRIVATE => __('对私账户', [], 'enums/bill'),
            self::P_CARD => __('P卡', [], 'enums/bill'),
        ];
    }
}