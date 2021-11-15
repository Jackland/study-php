<?php

namespace App\Enums\Pay;

use Framework\Enum\BaseEnum;

class VirtualPayType extends BaseEnum
{
    const PURCHASE_ORDER_PAY = 1;
    const RMA_REFUND = 2;
    const REBATE = 3;
    const STORAGE_FEE = 4;
    const SAFEGUARD_PAY = 5;// 保障服务费
    const SAFEGUARD_REFUND = 6;// 保障服务费退款
    const SAFEGUARD_RECHARGE = 7;// 保障服务费理赔款
    const STORAGE_FEE_REFUND = 8;// 仓租费退款

    public static function getViewItems()
    {
        return [
            static::PURCHASE_ORDER_PAY => 'Virtual Pay',
            static::RMA_REFUND => 'RMA Virtual Recharge',
            static::REBATE => 'Rebate Recharge',
            static::STORAGE_FEE => 'Storage Fee Payment',
            static::SAFEGUARD_PAY => 'Protection Service Fee Payment',
            static::SAFEGUARD_REFUND => 'Protection Service Fee Refund',
            static::SAFEGUARD_RECHARGE => 'Added to Account Balance',
            static::STORAGE_FEE_REFUND => 'Storage Fee Refund',
        ];
    }

    // 收入
    public static function getRevenueType()
    {
        return [
            static::RMA_REFUND,
            static::REBATE,
            static::SAFEGUARD_REFUND,
            static::SAFEGUARD_RECHARGE,
            static::STORAGE_FEE_REFUND,
        ];
    }

    public static function getPaymentType()
    {
        return [
            static::PURCHASE_ORDER_PAY,
            static::STORAGE_FEE,
            static::SAFEGUARD_PAY
        ];
    }
}
