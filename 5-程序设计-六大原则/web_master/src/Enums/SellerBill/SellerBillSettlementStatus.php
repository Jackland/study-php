<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算状态
 * tb_seller_bill --> settlement_status
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerBillSettlementStatus extends BaseEnum
{
    const GOING = 0; // 进行中
    const IN_THE_SETTLEMENT = 1; // 结算中
    const ALREADY_SETTLED = 2; // 已结算

    // 获取进行中的状态
    public static function getAllGoingStatus()
    {
        return [
            self::GOING,
            self::IN_THE_SETTLEMENT
        ];
    }

    public static function getViewItems()
    {
        return [
            self::GOING => __('正在进行', [], 'enums/bill'),
            self::IN_THE_SETTLEMENT => __('结算中', [], 'enums/bill'),
            self::ALREADY_SETTLED => __('已结算', [], 'enums/bill')
        ];
    }
}