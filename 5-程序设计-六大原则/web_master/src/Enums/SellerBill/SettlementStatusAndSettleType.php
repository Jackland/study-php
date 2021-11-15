<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 本枚举不实际对应数据表中某个特殊字段，而是表中两字段组合成联合状态
 * tb_seller_bill --> settle_type && settlement_status
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SettlementStatusAndSettleType extends BaseEnum
{
    const GOING = 0; // 进行中：settlement_status = 0
    const IN_THE_SETTLEMENT = 1; // 结算中：settlement_status = 1
    const ALREADY_SETTLED_DIRECT = 2; // 已结算(直接结算)：settlement_status = 2 && settle_type  = 0
    const ALREADY_SETTLED_SWITCH = 3; // 已结算(装期初余额)：settlement_status = 2 && settle_type = 1

    public static function getViewItems()
    {
        return [
            self::GOING => __('正在进行', [], 'enums/bill'),
            self::IN_THE_SETTLEMENT => __('结算中', [], 'enums/bill'),
            self::ALREADY_SETTLED_DIRECT => __('已结算', [], 'enums/bill'),
            self::ALREADY_SETTLED_SWITCH => __('已结算（转期初金额）', [], 'enums/bill'),
        ];
    }

    /**
     * 通过结算状态和账单结算方式组合账单展示状态
     *
     * @param int $settlementStatus 结算状态
     * @param int $settleType 账单结算方式
     *
     * @return string
     */
    public static function transitionStatusAndType($settlementStatus, $settleType)
    {
        if ($settlementStatus == SellerBillSettlementStatus::GOING) {
            $trans = self::getDescription(self::GOING);
        } elseif ($settlementStatus == SellerBillSettlementStatus::IN_THE_SETTLEMENT) {
            $trans = self::getDescription(self::IN_THE_SETTLEMENT);
        } elseif ($settlementStatus == SellerBillSettlementStatus::ALREADY_SETTLED && $settleType == SellerBillSettleType::DIRECT_SETTLEMENT) {
            $trans = self::getDescription(self::ALREADY_SETTLED_DIRECT);
        } elseif ($settlementStatus == SellerBillSettlementStatus::ALREADY_SETTLED && $settleType == SellerBillSettleType::SWITCH_BALANCE) {
            $trans = self::getDescription(self::ALREADY_SETTLED_SWITCH);
        } else {
            $trans = __('未知状态', [], 'enums/bill');
        }

        return $trans;
    }
}